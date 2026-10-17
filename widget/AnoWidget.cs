// Ano desktop widget — reads /api/widget from the Ano web app and shows a summary.
// Build: build.cmd (uses csc.exe bundled with Windows, C# 5).
//
// Rendering: everything is drawn with GDI+ into a 32bpp premultiplied DIB and BitBlt-ed to the window,
// so per-pixel alpha survives and the Windows acrylic backdrop shows through (GDI/TextRenderer can't do alpha).
using System;
using System.Collections;
using System.Collections.Generic;
using System.Diagnostics;
using System.Drawing;
using System.Drawing.Drawing2D;
using System.Drawing.Imaging;
using System.Drawing.Text;
using System.Globalization;
using System.IO;
using System.Net;
using System.Runtime.InteropServices;
using System.Security.Cryptography;
using System.Text;
using System.Threading.Tasks;
using System.Web.Script.Serialization;
using System.Windows.Forms;
using Microsoft.Win32;

class Doc { public string Type, Title; public int Days; }
class Ev { public string Title; public DateTime Date; }

// Palette follows Windows light/dark mode and accent color (or a forced mode from widget.ini).
static class Theme
{
	public static Color Bg, Card, Line, Fg, Muted, Dim, Accent, Green, Red, Amber;
	public static Color MenuBg, MenuLine; // opaque — GDI menus can't do alpha
	public static bool IsDark;

	public static void Load(string mode)
	{
		IsDark = mode == "dark" || (mode != "light" && SystemIsDark());
		if (IsDark) {
			Bg = Color.FromArgb(28, 30, 36);
			Card = Color.FromArgb(18, 255, 255, 255);
			Line = Color.FromArgb(26, 255, 255, 255);
			Fg = Color.FromArgb(240, 242, 246);
			Muted = Color.FromArgb(160, 167, 181);
			Dim = Color.FromArgb(112, 119, 133);
			Green = Color.FromArgb(52, 211, 153);
			Red = Color.FromArgb(248, 113, 113);
			Amber = Color.FromArgb(251, 191, 36);
			MenuBg = Color.FromArgb(36, 39, 47);
			MenuLine = Color.FromArgb(56, 60, 70);
		} else {
			Bg = Color.FromArgb(246, 247, 250);
			Card = Color.FromArgb(150, 255, 255, 255);
			Line = Color.FromArgb(20, 0, 0, 0);
			Fg = Color.FromArgb(17, 24, 39);
			Muted = Color.FromArgb(90, 99, 115);
			Dim = Color.FromArgb(140, 148, 162);
			Green = Color.FromArgb(5, 150, 105);
			Red = Color.FromArgb(220, 38, 38);
			Amber = Color.FromArgb(217, 119, 6);
			MenuBg = Color.FromArgb(249, 250, 251);
			MenuLine = Color.FromArgb(226, 230, 236);
		}
		Color a = SystemAccent();
		// lighten accent on dark background, darken on light one, for readable contrast
		Accent = IsDark ? Blend(a, Color.White, 0.35) : Blend(a, Color.Black, 0.1);
	}

	static bool SystemIsDark()
	{
		using (var k = Registry.CurrentUser.OpenSubKey(@"Software\Microsoft\Windows\CurrentVersion\Themes\Personalize")) {
			object v = k != null ? k.GetValue("AppsUseLightTheme") : null;
			return v is int && (int) v == 0;
		}
	}

	static Color SystemAccent()
	{
		using (var k = Registry.CurrentUser.OpenSubKey(@"Software\Microsoft\Windows\DWM")) {
			object v = k != null ? k.GetValue("AccentColor") : null;
			if (!(v is int)) return Color.FromArgb(0, 120, 212);
			int abgr = (int) v;
			return Color.FromArgb(abgr & 0xFF, (abgr >> 8) & 0xFF, (abgr >> 16) & 0xFF);
		}
	}

	static Color Blend(Color a, Color b, double t)
	{
		return Color.FromArgb((int) (a.R + (b.R - a.R) * t), (int) (a.G + (b.G - a.G) * t), (int) (a.B + (b.B - a.B) * t));
	}
}

class ThemeMenuColors : ProfessionalColorTable
{
	public override Color ToolStripDropDownBackground { get { return Theme.MenuBg; } }
	public override Color ImageMarginGradientBegin { get { return Theme.MenuBg; } }
	public override Color ImageMarginGradientMiddle { get { return Theme.MenuBg; } }
	public override Color ImageMarginGradientEnd { get { return Theme.MenuBg; } }
	public override Color MenuBorder { get { return Theme.MenuLine; } }
	public override Color MenuItemBorder { get { return Theme.MenuLine; } }
	public override Color MenuItemSelected { get { return Theme.MenuLine; } }
	public override Color SeparatorDark { get { return Theme.MenuLine; } }
	public override Color SeparatorLight { get { return Theme.MenuLine; } }
	public override Color CheckBackground { get { return Theme.MenuLine; } }
	public override Color CheckSelectedBackground { get { return Theme.MenuLine; } }
	public override Color CheckPressedBackground { get { return Theme.MenuLine; } }
}

static class Native
{
	[DllImport("dwmapi.dll")] public static extern int DwmSetWindowAttribute(IntPtr hwnd, int attr, ref int value, int size);
	[DllImport("dwmapi.dll")] public static extern int DwmExtendFrameIntoClientArea(IntPtr hwnd, ref Margins m);
	[DllImport("user32.dll")] public static extern int SetWindowCompositionAttribute(IntPtr hwnd, ref WinCompAttrData data);
	[DllImport("gdi32.dll")] public static extern IntPtr CreateCompatibleDC(IntPtr hdc);
	[DllImport("gdi32.dll")] public static extern IntPtr CreateDIBSection(IntPtr hdc, ref BitmapInfoHeader bmi, uint usage, out IntPtr bits, IntPtr section, uint offset);
	[DllImport("gdi32.dll")] public static extern IntPtr SelectObject(IntPtr hdc, IntPtr obj);
	[DllImport("gdi32.dll")] public static extern bool DeleteObject(IntPtr obj);
	[DllImport("gdi32.dll")] public static extern bool DeleteDC(IntPtr hdc);
	[DllImport("gdi32.dll")] public static extern bool BitBlt(IntPtr dst, int x, int y, int w, int h, IntPtr src, int sx, int sy, int rop);
	[DllImport("user32.dll")] public static extern bool SetProcessDPIAware();

	[StructLayout(LayoutKind.Sequential)] public struct Margins { public int Left, Right, Top, Bottom; }
	[StructLayout(LayoutKind.Sequential)] public struct AccentPolicy { public int AccentState, AccentFlags, GradientColor, AnimationId; }
	[StructLayout(LayoutKind.Sequential)] public struct WinCompAttrData { public int Attribute; public IntPtr Data; public int SizeOfData; }
	[StructLayout(LayoutKind.Sequential)]
	public struct BitmapInfoHeader
	{
		public int biSize, biWidth, biHeight;
		public short biPlanes, biBitCount;
		public int biCompression, biSizeImage, biXPelsPerMeter, biYPelsPerMeter, biClrUsed, biClrImportant;
	}

	public static int WindowsBuild()
	{
		using (var k = Registry.LocalMachine.OpenSubKey(@"SOFTWARE\Microsoft\Windows NT\CurrentVersion")) {
			int b;
			return k != null && int.TryParse(k.GetValue("CurrentBuildNumber") as string, out b) ? b : 0;
		}
	}
}

class WidgetForm : Form
{
	const string RunKey = @"Software\Microsoft\Windows\CurrentVersion\Run";
	static readonly CultureInfo Cz = new CultureInfo("cs-CZ");
	static readonly Dictionary<string, string> DocIcons = new Dictionary<string, string> {
		{ "stk", "" },        // Repair (wrench)
		{ "insurance", "" },  // Shield
		{ "vignette", "" },   // Tag
	};

	readonly string iniPath;
	readonly Dictionary<string, string> cfg;
	readonly float scale;
	readonly Font fTitle, fSmall, fText, fBig, fTiny, fIcon, fIconSmall;
	readonly int build = Native.WindowsBuild();

	// data
	bool hasData, loading;
	string error, appUrl;
	decimal income, expense, balance;
	DateTime month = DateTime.Now, updated;
	List<Doc> docs = new List<Doc>();
	List<Ev> events = new List<Ev>();

	// interaction
	Rectangle[] buttons = new Rectangle[3]; // 0 pin, 1 refresh, 2 close
	int hover = -1;
	Point dragStart;
	bool dragging;
	ToolStripMenuItem miTop, miLock, miAutostart, miGlass;

	public WidgetForm(string iniPath)
	{
		this.iniPath = iniPath;
		cfg = LoadIni(iniPath);
		ProtectPassword();
		Theme.Load(Get("theme"));
		SystemEvents.UserPreferenceChanged += OnSystemThemeChanged;

		using (var g = CreateGraphics()) scale = g.DpiX / 96f;
		string ui = FontOr("Segoe UI Variable Text", "Segoe UI");
		string uiBold = FontOr("Segoe UI Variable Text Semibold", "Segoe UI Semibold");
		string display = FontOr("Segoe UI Variable Display Semibold", "Segoe UI Semibold");
		string icons = FontOr("Segoe Fluent Icons", "Segoe MDL2 Assets");
		fTitle = Px(display, 10.5f);
		fSmall = Px(uiBold, 7.5f);
		fText = Px(ui, 9f);
		fBig = Px(display, 21f);
		fTiny = Px(ui, 7.5f);
		fIcon = Px(icons, 9f);
		fIconSmall = Px(icons, 8f);

		FormBorderStyle = FormBorderStyle.None;
		ShowInTaskbar = false;
		StartPosition = FormStartPosition.Manual;
		BackColor = Color.Black;
		Width = S(300);
		Height = S(120);
		TopMost = Get("topmost") == "1";
		int op;
		if (int.TryParse(Get("opacity"), out op) && op < 100) Opacity = op / 100.0;

		Rectangle wa = Screen.PrimaryScreen.WorkingArea;
		int x, y;
		Location = int.TryParse(Get("x"), out x) && int.TryParse(Get("y"), out y)
			? new Point(x, y)
			: new Point(wa.Right - Width - S(20), wa.Top + S(20));

		BuildMenu();
		ApplyTheme();

		var timer = new Timer { Interval = 5 * 60 * 1000 };
		timer.Tick += delegate { Reload(); };
		timer.Start();
		Reload();
	}

	int S(float v) { return (int) Math.Round(v * scale); }

	// Fonts in pixels, so GDI+ drawing into the DIB scales with DPI.
	Font Px(string family, float pt) { return new Font(family, pt * 96f / 72f * scale, GraphicsUnit.Pixel); }

	static string FontOr(string name, string fallback)
	{
		using (var f = new Font(name, 9f)) return f.Name == name ? name : fallback;
	}

	string Get(string key)
	{
		string v;
		return cfg.TryGetValue(key, out v) ? v : "";
	}

	void Set(string key, string value)
	{
		cfg[key] = value;
		SaveIni();
	}

	bool Glass { get { return Get("glass") != "0"; } }

	// ---------- window chrome ----------

	protected override CreateParams CreateParams
	{
		get {
			var cp = base.CreateParams;
			cp.ClassStyle |= 0x20000;   // CS_DROPSHADOW
			cp.ExStyle |= 0x80;         // WS_EX_TOOLWINDOW — hidden from Alt+Tab
			return cp;
		}
	}

	protected override void OnHandleCreated(EventArgs e)
	{
		base.OnHandleCreated(e);
		int round = 2; // DWMWCP_ROUND (Windows 11)
		Native.DwmSetWindowAttribute(Handle, 33, ref round, 4);
		ApplyTheme();
	}

	protected override void OnFormClosed(FormClosedEventArgs e)
	{
		SystemEvents.UserPreferenceChanged -= OnSystemThemeChanged;
		base.OnFormClosed(e);
	}

	void OnSystemThemeChanged(object sender, UserPreferenceChangedEventArgs e)
	{
		Theme.Load(Get("theme"));
		ApplyTheme();
	}

	void ApplyTheme()
	{
		if (!IsHandleCreated) return;
		int dark = Theme.IsDark ? 1 : 0;
		Native.DwmSetWindowAttribute(Handle, 20, ref dark, 4); // DWMWA_USE_IMMERSIVE_DARK_MODE — tints the backdrop
		int border = ColorTranslator.ToWin32(Theme.MenuLine);
		Native.DwmSetWindowAttribute(Handle, 34, ref border, 4); // DWMWA_BORDER_COLOR (Windows 11)
		ApplyBackdrop();
		if (ContextMenuStrip != null) ColorItems(ContextMenuStrip.Items);
		Invalidate();
	}

	// Win11 22H2+: system acrylic backdrop. Older Windows: undocumented accent-policy acrylic.
	void ApplyBackdrop()
	{
		bool on = Glass;
		if (build >= 22621) {
			var m = on ? new Native.Margins { Left = -1, Right = -1, Top = -1, Bottom = -1 } : new Native.Margins();
			Native.DwmExtendFrameIntoClientArea(Handle, ref m);
			int type = on ? 3 : 1; // DWMSBT_TRANSIENTWINDOW (acrylic) : DWMSBT_NONE
			Native.DwmSetWindowAttribute(Handle, 38, ref type, 4);
		} else {
			var accent = new Native.AccentPolicy {
				AccentState = on ? 4 : 0, // ACCENT_ENABLE_ACRYLICBLURBEHIND
				GradientColor = (Theme.IsDark ? 0x99 << 24 : 0xAA << 24) | (Theme.Bg.B << 16) | (Theme.Bg.G << 8) | Theme.Bg.R,
			};
			int size = Marshal.SizeOf(accent);
			IntPtr ptr = Marshal.AllocHGlobal(size);
			Marshal.StructureToPtr(accent, ptr, false);
			var data = new Native.WinCompAttrData { Attribute = 19, Data = ptr, SizeOfData = size };
			Native.SetWindowCompositionAttribute(Handle, ref data);
			Marshal.FreeHGlobal(ptr);
		}
	}

	static void ColorItems(ToolStripItemCollection items)
	{
		foreach (ToolStripItem i in items) {
			i.ForeColor = Theme.Fg;
			var mi = i as ToolStripMenuItem;
			if (mi != null) {
				mi.DropDown.BackColor = Theme.MenuBg;
				ColorItems(mi.DropDownItems);
			}
		}
	}

	void SetThemeMode(string mode)
	{
		Set("theme", mode);
		Theme.Load(mode);
		ApplyTheme();
	}

	void BuildMenu()
	{
		var menu = new ContextMenuStrip { Renderer = new ToolStripProfessionalRenderer(new ThemeMenuColors()), ShowImageMargin = false, ShowCheckMargin = true };
		menu.Font = new Font("Segoe UI", 9f);

		miTop = new ToolStripMenuItem("Vždy navrchu", null, delegate { ToggleTopMost(); }) { Checked = TopMost };
		miLock = new ToolStripMenuItem("Zamknout pozici", null, delegate {
			miLock.Checked = !miLock.Checked;
			Set("locked", miLock.Checked ? "1" : "0");
		}) { Checked = Get("locked") == "1" };
		miGlass = new ToolStripMenuItem("Průsvitné pozadí", null, delegate {
			Set("glass", Glass ? "0" : "1");
			miGlass.Checked = Glass;
			ApplyTheme();
		}) { Checked = Glass };

		var miOpacity = new ToolStripMenuItem("Průhlednost");
		foreach (int p in new[] { 100, 90, 80, 70, 60 }) {
			int pct = p;
			miOpacity.DropDownItems.Add(new ToolStripMenuItem(pct + " %", null, delegate {
				Opacity = pct / 100.0;
				Set("opacity", pct.ToString());
			}));
		}
		miOpacity.DropDownOpening += delegate {
			foreach (ToolStripMenuItem i in miOpacity.DropDownItems) i.Checked = i.Text == (int) Math.Round(Opacity * 100) + " %";
		};

		var miTheme = new ToolStripMenuItem("Motiv");
		var modes = new[] { new[] { "", "Podle systému" }, new[] { "light", "Světlý" }, new[] { "dark", "Tmavý" } };
		foreach (var m in modes) {
			string mode = m[0];
			var item = new ToolStripMenuItem(m[1], null, delegate { SetThemeMode(mode); });
			item.Tag = mode;
			miTheme.DropDownItems.Add(item);
		}
		miTheme.DropDownOpening += delegate {
			foreach (ToolStripMenuItem i in miTheme.DropDownItems) i.Checked = (string) i.Tag == Get("theme");
		};

		miAutostart = new ToolStripMenuItem("Spustit s Windows", null, delegate { ToggleAutostart(); }) { Checked = IsAutostart() };

		menu.Items.Add(miTop);
		menu.Items.Add(miLock);
		menu.Items.Add(miGlass);
		menu.Items.Add(miOpacity);
		menu.Items.Add(miTheme);
		menu.Items.Add(miAutostart);
		menu.Items.Add(new ToolStripSeparator());
		menu.Items.Add("Obnovit", null, delegate { Reload(); });
		menu.Items.Add("Otevřít aplikaci", null, delegate { OpenApp(); });
		menu.Items.Add(new ToolStripSeparator());
		menu.Items.Add("Konec", null, delegate { Close(); });
		ContextMenuStrip = menu;
	}

	void ToggleTopMost()
	{
		TopMost = !TopMost;
		miTop.Checked = TopMost;
		Set("topmost", TopMost ? "1" : "0");
		Invalidate();
	}

	bool IsAutostart()
	{
		using (var k = Registry.CurrentUser.OpenSubKey(RunKey)) {
			return k != null && k.GetValue("AnoWidget") != null;
		}
	}

	void ToggleAutostart()
	{
		using (var k = Registry.CurrentUser.CreateSubKey(RunKey)) {
			if (IsAutostart()) k.DeleteValue("AnoWidget", false);
			else k.SetValue("AnoWidget", "\"" + Application.ExecutablePath + "\"");
		}
		miAutostart.Checked = IsAutostart();
	}

	// ---------- mouse ----------

	protected override void OnMouseMove(MouseEventArgs e)
	{
		base.OnMouseMove(e);
		if (dragging) {
			Location = new Point(Left + e.X - dragStart.X, Top + e.Y - dragStart.Y);
			return;
		}
		int h = Array.FindIndex(buttons, r => r.Contains(e.Location));
		if (h != hover) {
			hover = h;
			Cursor = h >= 0 ? Cursors.Hand : Cursors.Default;
			Invalidate();
		}
	}

	protected override void OnMouseLeave(EventArgs e)
	{
		base.OnMouseLeave(e);
		if (hover != -1) { hover = -1; Invalidate(); }
	}

	protected override void OnMouseDown(MouseEventArgs e)
	{
		base.OnMouseDown(e);
		if (e.Button != MouseButtons.Left) return;
		if (hover == 0) { ToggleTopMost(); return; }
		if (hover == 1) { Reload(); return; }
		if (hover == 2) { Close(); return; }
		if (Get("locked") != "1") { dragging = true; dragStart = e.Location; }
	}

	protected override void OnMouseUp(MouseEventArgs e)
	{
		base.OnMouseUp(e);
		if (!dragging) return;
		dragging = false;
		cfg["x"] = Left.ToString();
		cfg["y"] = Top.ToString();
		SaveIni();
	}

	protected override void OnMouseDoubleClick(MouseEventArgs e)
	{
		base.OnMouseDoubleClick(e);
		if (hover < 0) OpenApp();
	}

	void OpenApp()
	{
		Process.Start(string.IsNullOrEmpty(appUrl) ? Get("url") : appUrl);
	}

	// ---------- data ----------

	async void Reload()
	{
		if (loading) return;
		loading = true;
		Invalidate();
		try {
			string json = await Task.Run(() => Fetch());
			Parse(new JavaScriptSerializer().Deserialize<Dictionary<string, object>>(json));
			error = null;
			hasData = true;
			updated = DateTime.Now;
		} catch (WebException ex) {
			var resp = ex.Response as HttpWebResponse;
			error = resp != null && resp.StatusCode == HttpStatusCode.Unauthorized
				? "Špatný e-mail nebo heslo ve widget.ini"
				: "Server je nedostupný";
		} catch (Exception ex) {
			error = ex.Message;
		}
		loading = false;
		Relayout();
	}

	string Fetch()
	{
		string pass = Encoding.UTF8.GetString(ProtectedData.Unprotect(
			Convert.FromBase64String(Get("password_dpapi")), null, DataProtectionScope.CurrentUser));
		using (var wc = new WebClient()) {
			wc.Encoding = Encoding.UTF8;
			wc.Headers[HttpRequestHeader.Authorization] = "Basic " +
				Convert.ToBase64String(Encoding.UTF8.GetBytes(Get("email") + ":" + pass));
			return wc.DownloadString(Get("url").TrimEnd('/') + "/api/widget");
		}
	}

	void Parse(Dictionary<string, object> d)
	{
		appUrl = (string) d["appUrl"];
		month = DateTime.ParseExact((string) d["month"], "yyyy-MM", CultureInfo.InvariantCulture);
		income = Convert.ToDecimal(d["income"]);
		expense = Convert.ToDecimal(d["expense"]);
		balance = Convert.ToDecimal(d["balance"]);

		docs = new List<Doc>();
		foreach (Dictionary<string, object> x in (ArrayList) d["documents"]) {
			docs.Add(new Doc {
				Type = x.ContainsKey("type") ? (string) x["type"] : "",
				Title = (string) x["title"],
				Days = Convert.ToInt32(x["days"]),
			});
		}
		events = new List<Ev>();
		foreach (Dictionary<string, object> x in (ArrayList) d["events"]) {
			events.Add(new Ev { Title = (string) x["title"], Date = DateTime.ParseExact((string) x["date"], "yyyy-MM-dd", CultureInfo.InvariantCulture) });
		}
	}

	static string Money(decimal v)
	{
		return v.ToString("N0", Cz) + " Kč";
	}

	// ---------- rendering ----------

	void Relayout()
	{
		using (var g = CreateGraphics()) Height = Render(g, false);
		Invalidate();
	}

	protected override void OnPaintBackground(PaintEventArgs e)
	{
		// everything is painted in OnPaint
	}

	protected override void OnPaint(PaintEventArgs e)
	{
		int w = ClientSize.Width, h = ClientSize.Height;
		if (w <= 0 || h <= 0) return;

		var bmi = new Native.BitmapInfoHeader { biSize = 40, biWidth = w, biHeight = -h, biPlanes = 1, biBitCount = 32 };
		IntPtr bits;
		IntPtr dib = Native.CreateDIBSection(IntPtr.Zero, ref bmi, 0, out bits, IntPtr.Zero, 0);
		IntPtr mem = Native.CreateCompatibleDC(IntPtr.Zero);
		IntPtr old = Native.SelectObject(mem, dib);
		try {
			using (var bmp = new Bitmap(w, h, w * 4, PixelFormat.Format32bppPArgb, bits))
			using (var g = Graphics.FromImage(bmp)) {
				g.SmoothingMode = SmoothingMode.AntiAlias;
				g.TextRenderingHint = TextRenderingHint.AntiAliasGridFit;
				g.Clear(Glass ? Color.FromArgb(Theme.IsDark ? 90 : 120, Theme.Bg) : Theme.Bg);
				Render(g, true);
			}
			IntPtr hdc = e.Graphics.GetHdc();
			Native.BitBlt(hdc, 0, 0, w, h, mem, 0, 0, 0x00CC0020); // SRCCOPY keeps alpha
			e.Graphics.ReleaseHdc(hdc);
		} finally {
			Native.SelectObject(mem, old);
			Native.DeleteObject(dib);
			Native.DeleteDC(mem);
		}
	}

	// Draws the widget (or only measures it when draw == false); returns total height.
	int Render(Graphics g, bool draw)
	{
		int pad = S(16), w = Width - 2 * pad, y = S(14);

		// header: logo, title, month, buttons
		if (draw) {
			var logo = new Rectangle(pad, y, S(26), S(26));
			Fill(g, Theme.Accent, logo, S(8));
			Draw(g, "", fIcon, Color.White, logo, StringAlignment.Center); // Car
			Draw(g, "Ano", fTitle, Theme.Fg, new Rectangle(pad + S(34), y - S(1), S(120), S(16)), StringAlignment.Near);
			Draw(g, month.ToString("MMMM yyyy", Cz), fTiny, Theme.Muted, new Rectangle(pad + S(34), y + S(14), S(140), S(14)), StringAlignment.Near);

			string[] icons = { TopMost ? "" : "", "", "" };
			for (int i = 0; i < 3; i++) {
				var r = new Rectangle(Width - pad - S(28) * (3 - i) + S(4), y, S(26), S(26));
				buttons[i] = r;
				if (hover == i) Fill(g, Theme.Line, r, S(7));
				Color c = (i == 0 && TopMost) || (i == 1 && loading) ? Theme.Accent : hover == i ? Theme.Fg : Theme.Muted;
				Draw(g, icons[i], i == 2 ? fIconSmall : fIcon, c, r, StringAlignment.Center);
			}
		}
		y += S(42);

		if (error != null || !hasData) {
			if (draw) {
				var r = new Rectangle(pad, y, w, S(44));
				Fill(g, Theme.Card, r, S(10));
				Draw(g, error ?? "Načítám…", fText, error != null ? Theme.Red : Theme.Muted, Rectangle.Inflate(r, -S(12), 0), StringAlignment.Near, true);
			}
			y += S(44);
			if (!hasData) return y + S(16);
			y += S(10);
		}

		// balance card
		if (draw) {
			Fill(g, Theme.Card, new Rectangle(pad, y, w, S(106)), S(12));
			int ix = pad + S(14), iw = w - S(28);
			Draw(g, "ZŮSTATEK MĚSÍCE", fSmall, Theme.Muted, new Rectangle(ix, y + S(10), iw, S(16)), StringAlignment.Near);
			Draw(g, (balance > 0 ? "+" : "") + Money(balance), fBig, balance >= 0 ? Theme.Green : Theme.Red, new Rectangle(ix - S(2), y + S(26), iw, S(36)), StringAlignment.Near);

			// income vs expense ratio bar
			var bar = new Rectangle(ix, y + S(70), iw, S(5));
			Fill(g, Theme.Line, bar, S(3));
			decimal total = income + expense;
			if (total > 0) {
				int incW = (int) (bar.Width * (income / total));
				if (incW > 0) Fill(g, Theme.Green, new Rectangle(bar.X, bar.Y, incW, bar.Height), S(3));
				if (bar.Width - incW > 0) Fill(g, Theme.Red, new Rectangle(bar.X + incW, bar.Y, bar.Width - incW, bar.Height), S(3));
			}
			Draw(g, "", fIconSmall, Theme.Green, new Rectangle(ix, y + S(80), S(14), S(18)), StringAlignment.Near);   // ChevronUp
			Draw(g, Money(income), fTiny, Theme.Green, new Rectangle(ix + S(14), y + S(80), iw / 2, S(18)), StringAlignment.Near);
			Draw(g, Money(expense), fTiny, Theme.Red, new Rectangle(ix + iw / 2, y + S(80), iw / 2 - S(14), S(18)), StringAlignment.Far);
			Draw(g, "", fIconSmall, Theme.Red, new Rectangle(ix + iw - S(12), y + S(80), S(14), S(18)), StringAlignment.Far); // ChevronDown
		}
		y += S(106) + S(18);

		// documents
		y = Section(g, draw, "", "DOKUMENTY", docs.Count, y); // Page
		if (docs.Count == 0) {
			if (draw) Draw(g, "Nic nevyprší do 60 dní", fText, Theme.Dim, new Rectangle(pad, y, w, S(24)), StringAlignment.Near);
			y += S(28);
		}
		foreach (var d in docs) {
			if (draw) {
				Color c = d.Days < 0 ? Theme.Red : d.Days <= 14 ? Theme.Amber : Theme.Green;
				string badge = d.Days < 0 ? "propadlé" : d.Days == 0 ? "dnes" : "za " + d.Days + " d";
				int bw = (int) g.MeasureString(badge, fSmall).Width + S(12);
				string icon;
				if (!DocIcons.TryGetValue(d.Type, out icon)) icon = "";

				var ico = new Rectangle(pad, y + S(2), S(26), S(26));
				Fill(g, Color.FromArgb(38, c), ico, S(7));
				Draw(g, icon, fIconSmall, c, ico, StringAlignment.Center);
				Draw(g, d.Title, fText, Theme.Fg, new Rectangle(pad + S(36), y, w - bw - S(44), S(30)), StringAlignment.Near);
				var pill = new Rectangle(pad + w - bw, y + S(6), bw, S(18));
				Fill(g, Color.FromArgb(38, c), pill, S(9));
				Draw(g, badge, fSmall, c, pill, StringAlignment.Center);
			}
			y += S(32);
		}
		y += S(12);

		// events
		y = Section(g, draw, "", "UDÁLOSTI · 7 DNÍ", events.Count, y); // Calendar
		if (events.Count == 0) {
			if (draw) Draw(g, "Žádné události", fText, Theme.Dim, new Rectangle(pad, y, w, S(24)), StringAlignment.Near);
			y += S(28);
		}
		foreach (var ev in events) {
			if (draw) {
				bool today = ev.Date.Date == DateTime.Today;
				var chip = new Rectangle(pad, y + S(2), S(26), S(26));
				Fill(g, today ? Color.FromArgb(50, Theme.Accent) : Theme.Card, chip, S(7));
				Draw(g, ev.Date.Day.ToString(), fSmall, today ? Theme.Accent : Theme.Fg, chip, StringAlignment.Center);
				Draw(g, ev.Title, fText, Theme.Fg, new Rectangle(pad + S(36), y, w - S(110), S(30)), StringAlignment.Near);
				Draw(g, today ? "dnes" : ev.Date.ToString("ddd d. M.", Cz), fTiny, today ? Theme.Accent : Theme.Muted, new Rectangle(pad + w - S(70), y, S(70), S(30)), StringAlignment.Far);
			}
			y += S(32);
		}

		// footer
		y += S(8);
		if (draw) {
			string foot = "Aktualizováno " + updated.ToString("HH:mm") + (TopMost ? "  ·  připnuto" : "");
			Draw(g, foot, fTiny, Theme.Dim, new Rectangle(pad, y, w, S(16)), StringAlignment.Near);
		}
		return y + S(28);
	}

	int Section(Graphics g, bool draw, string icon, string title, int count, int y)
	{
		int pad = S(16);
		if (draw) {
			Draw(g, icon, fIconSmall, Theme.Muted, new Rectangle(pad, y, S(16), S(18)), StringAlignment.Near);
			Draw(g, title, fSmall, Theme.Muted, new Rectangle(pad + S(18), y, S(200), S(18)), StringAlignment.Near);
			if (count > 0) Draw(g, count.ToString(), fSmall, Theme.Dim, new Rectangle(Width - pad - S(40), y, S(40), S(18)), StringAlignment.Far);
		}
		return y + S(26);
	}

	static void Draw(Graphics g, string s, Font f, Color c, Rectangle r, StringAlignment align, bool wrap = false)
	{
		using (var sf = new StringFormat(wrap ? 0 : StringFormatFlags.NoWrap))
		using (var b = new SolidBrush(c)) {
			sf.Alignment = align;
			sf.LineAlignment = StringAlignment.Center;
			sf.Trimming = StringTrimming.EllipsisCharacter;
			g.DrawString(s, f, b, r, sf);
		}
	}

	static void Fill(Graphics g, Color c, Rectangle r, int radius)
	{
		int d = Math.Min(radius * 2, Math.Min(r.Width, r.Height));
		if (d <= 0) return;
		using (var path = new GraphicsPath())
		using (var b = new SolidBrush(c)) {
			path.AddArc(r.X, r.Y, d, d, 180, 90);
			path.AddArc(r.Right - d, r.Y, d, d, 270, 90);
			path.AddArc(r.Right - d, r.Bottom - d, d, d, 0, 90);
			path.AddArc(r.X, r.Bottom - d, d, d, 90, 90);
			path.CloseFigure();
			g.FillPath(b, path);
		}
	}

	// ---------- config ----------

	// Plain "password=" from widget.ini is encrypted (Windows DPAPI, current user) on first run.
	void ProtectPassword()
	{
		string plain = Get("password");
		if (plain == "") return;
		cfg["password_dpapi"] = Convert.ToBase64String(ProtectedData.Protect(
			Encoding.UTF8.GetBytes(plain), null, DataProtectionScope.CurrentUser));
		cfg.Remove("password");
		SaveIni();
	}

	static Dictionary<string, string> LoadIni(string path)
	{
		var d = new Dictionary<string, string>();
		foreach (var raw in File.ReadAllLines(path, Encoding.UTF8)) {
			var line = raw.Trim();
			int i = line.IndexOf('=');
			if (i > 0 && !line.StartsWith("#")) d[line.Substring(0, i).Trim()] = line.Substring(i + 1).Trim();
		}
		return d;
	}

	void SaveIni()
	{
		var sb = new StringBuilder();
		foreach (var kv in cfg) sb.AppendLine(kv.Key + "=" + kv.Value);
		File.WriteAllText(iniPath, sb.ToString(), new UTF8Encoding(false));
	}
}

static class Program
{
	[STAThread]
	static void Main()
	{
		Native.SetProcessDPIAware();
		ServicePointManager.SecurityProtocol |= SecurityProtocolType.Tls12;
		string ini = Path.Combine(AppDomain.CurrentDomain.BaseDirectory, "widget.ini");
		if (!File.Exists(ini)) {
			MessageBox.Show("Chybí widget.ini vedle AnoWidget.exe (vzor: widget.ini.example).", "Ano widget");
			return;
		}
		Application.EnableVisualStyles();
		Application.Run(new WidgetForm(ini));
	}
}
