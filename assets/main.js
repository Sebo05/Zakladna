// Core bundle — loads on every page
import './main.css';
import netteForms from 'nette-forms';
import Alpine from 'alpinejs';
import {
	createIcons, createElement,
	House, Car, Wallet, ChartPie, ChartColumn, Calendar, CalendarDays, Search, Plus, Moon, Sun, LogOut,
	Fuel, Gauge, PiggyBank, Target, Wrench, Shield, ShieldCheck, Ticket, FileText, ChevronRight, ChevronLeft,
	ArrowUpRight, ArrowDownLeft, Pencil, Trash2, Check, X, Command, Monitor, Smartphone, Sparkles, Bell, Zap,
	Eye, EyeOff, ArrowRight, ArrowLeft, Receipt, TrendingUp, TrendingDown, Droplet, MapPin, Pin, Download,
	Lock, User, Mail, CircleAlert, Upload, Tag, Clock, Route, Banknote, CornerDownLeft, LayoutGrid,
	Link, ListChecks, ShoppingCart, Settings, UserMinus, RefreshCw, HousePlus, GripVertical, SlidersHorizontal, RotateCcw, Columns2, Menu,
} from 'lucide';

const icons = {
	House, Car, Wallet, ChartPie, ChartColumn, Calendar, CalendarDays, Search, Plus, Moon, Sun, LogOut,
	Fuel, Gauge, PiggyBank, Target, Wrench, Shield, ShieldCheck, Ticket, FileText, ChevronRight, ChevronLeft,
	ArrowUpRight, ArrowDownLeft, Pencil, Trash2, Check, X, Command, Monitor, Smartphone, Sparkles, Bell, Zap,
	Eye, EyeOff, ArrowRight, ArrowLeft, Receipt, TrendingUp, TrendingDown, Droplet, MapPin, Pin, Download,
	Lock, User, Mail, CircleAlert, Upload, Tag, Clock, Route, Banknote, CornerDownLeft, LayoutGrid,
	Link, ListChecks, ShoppingCart, Settings, UserMinus, RefreshCw, HousePlus, GripVertical, SlidersHorizontal, RotateCcw, Columns2, Menu,
};
const iconAttrs = { 'stroke-width': 1.75 };

// <i data-lucide="car"></i> → inline SVG
window.renderIcons = () => createIcons({ icons, attrs: iconAttrs });

// SVG markup for dynamic (Alpine) content, e.g. icon('Car')
const iconCache = {};
window.icon = (name) => {
	if (!icons[name]) return '';
	return iconCache[name] ??= createElement(icons[name], iconAttrs).outerHTML;
};

netteForms.initOnLoad();

// Theme toggle — paired with the inline init script in <head>
window.toggleTheme = () => {
	const next = document.documentElement.classList.contains('dark') ? 'light' : 'dark';
	localStorage.setItem('theme', next);
	document.documentElement.classList.toggle('dark', next === 'dark');
};

const norm = (s) => (s || '').normalize('NFD').replace(/\p{Diacritic}/gu, '').toLowerCase();

// Command palette (Ctrl/⌘+K). Static actions come from <script id="palette-actions">,
// full-text results from the search endpoint.
Alpine.data('palette', () => ({
	open: false,
	q: '',
	actions: [],
	remote: [],
	items: [],
	active: 0,
	timer: null,

	init() {
		this.searchUrl = this.$el.dataset.search;
		const el = document.getElementById('palette-actions');
		this.actions = el ? JSON.parse(el.textContent) : [];
		this.filter();
		window.addEventListener('keydown', (e) => {
			if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
				e.preventDefault();
				this.open ? this.close() : this.show();
			} else if (e.key === 'Escape' && this.open) {
				this.close();
			}
		});
		window.addEventListener('open-palette', () => this.show());
	},
	show() {
		this.open = true;
		this.q = '';
		this.remote = [];
		this.filter();
		this.$nextTick(() => this.$refs.input.focus());
	},
	close() {
		this.open = false;
	},
	filter() {
		const q = norm(this.q);
		const local = this.actions.filter((a) => !q || norm(a.title + ' ' + (a.keywords || '')).includes(q));
		this.items = [...local, ...this.remote];
		this.active = 0;
	},
	onInput() {
		this.filter();
		clearTimeout(this.timer);
		if (this.q.trim().length < 2) {
			this.remote = [];
			return;
		}
		this.timer = setTimeout(async () => {
			try {
				const res = await fetch(this.searchUrl + '?q=' + encodeURIComponent(this.q.trim()));
				this.remote = await res.json();
			} catch {
				this.remote = [];
			}
			this.filter();
		}, 150);
	},
	move(delta) {
		if (!this.items.length) return;
		this.active = (this.active + delta + this.items.length) % this.items.length;
		this.$nextTick(() => this.$refs.list.querySelector('[data-active="true"]')?.scrollIntoView({ block: 'nearest' }));
	},
	go(i) {
		const it = this.items[i ?? this.active];
		if (it) window.location.href = it.url;
	},
	showGroup(i) {
		return i === 0 || this.items[i - 1].group !== this.items[i].group;
	},
}));

// Customisable dashboard: reorder (drag & drop), width 1–3 columns, hide/show. Saved per user via signal.
Alpine.data('dashboardEditor', () => ({
	items: [],
	saved: '',
	editing: false,
	saving: false,
	dragKey: null,

	init() {
		this.items = JSON.parse(this.$el.dataset.layout).map(({ k, w, h }) => ({ k, w, h }));
		this.saved = JSON.stringify(this.items);
	},
	find(k) {
		return this.items.find((i) => i.k === k) || { w: 1, h: false };
	},
	pos(k) {
		return this.items.findIndex((i) => i.k === k);
	},
	width(k) {
		return this.find(k).w;
	},
	hidden(k) {
		return this.find(k).h;
	},
	classes(k) {
		const it = this.find(k);
		return [
			it.h && !this.editing ? 'hidden' : '',
			this.editing ? 'outline-2 outline-dashed outline-accent/40 outline-offset-4 cursor-grab select-none' : '',
			this.dragKey === k ? 'opacity-40' : '',
		].join(' ');
	},
	start() {
		this.editing = true;
	},
	cancel() {
		this.items = JSON.parse(this.saved);
		this.editing = false;
	},
	setWidth(k, w) {
		this.find(k).w = w;
	},
	toggle(k) {
		this.find(k).h = !this.find(k).h;
	},
	dragStart(e, k) {
		if (!this.editing) return;
		this.dragKey = k;
		e.dataTransfer.effectAllowed = 'move';
	},
	dragOver(k) {
		if (!this.dragKey || k === this.dragKey) return;
		const from = this.pos(this.dragKey);
		const to = this.pos(k);
		const [moved] = this.items.splice(from, 1);
		this.items.splice(to, 0, moved);
	},
	async save() {
		this.saving = true;
		try {
			const res = await fetch(this.$el.dataset.saveUrl, {
				method: 'POST',
				headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
				body: JSON.stringify(this.items),
			});
			if (!res.ok) throw new Error(res.status);
			this.saved = JSON.stringify(this.items);
			this.editing = false;
		} catch (err) {
			alert('Uložení se nepovedlo, zkus to znovu.');
		} finally {
			this.saving = false;
		}
	},
}));

// Password strength 0–4 (length, case mix, digits, symbols)
window.passwordScore = (p) => {
	if (!p) return 0;
	let s = 0;
	if (p.length >= 8) s++;
	if (p.length >= 12) s++;
	if (/[a-z]/.test(p) && /[A-Z]/.test(p)) s++;
	if (/\d/.test(p) && /[^A-Za-z0-9]/.test(p)) s++;
	return Math.max(p.length >= 6 ? 1 : 0, s);
};

window.Alpine = Alpine;
Alpine.start();

function initFileDrops() {
	document.querySelectorAll('label.file-drop').forEach((label) => {
		const input = label.querySelector('input[type="file"]');
		const nameEl = label.querySelector('.file-name');
		if (!input) return;

		const showName = () => {
			const has = input.files && input.files.length > 0;
			nameEl.textContent = has ? '✓ ' + input.files[0].name : '';
			nameEl.classList.toggle('hidden', !has);
			label.style.borderColor = has ? 'var(--pos)' : '';
		};
		input.addEventListener('change', showName);

		['dragenter', 'dragover'].forEach((ev) =>
			label.addEventListener(ev, (e) => {
				e.preventDefault();
				label.style.borderColor = 'var(--accent)';
			}),
		);
		['dragleave', 'drop'].forEach((ev) =>
			label.addEventListener(ev, (e) => {
				e.preventDefault();
				label.style.borderColor = '';
			}),
		);
		label.addEventListener('drop', (e) => {
			if (e.dataTransfer.files.length > 0) {
				input.files = e.dataTransfer.files;
				showName();
			}
		});
	});
}

function initConfirmLinks() {
	document.addEventListener('click', (e) => {
		const target = e.target.closest('[data-confirm]');
		if (target && !confirm(target.dataset.confirm)) {
			e.preventDefault();
		}
	});
}

// Page loader (logo tiles): shown while navigating away, delayed 150 ms in CSS so fast loads don't flash.
function initPageLoader() {
	const loader = Object.assign(document.createElement('div'), { className: 'page-loader', innerHTML: '<b><i></i><i></i><i></i><i></i></b>' });
	let skip = false;
	document.addEventListener('click', (e) => {
		const a = e.target.closest('a');
		// downloads / tel / mailto don't unload the page — loader would get stuck
		if (a && (a.hasAttribute('download') || /^(tel|mailto):/.test(a.getAttribute('href') || '') || /\/export\b/.test(a.href))) skip = true;
	});
	window.addEventListener('beforeunload', () => {
		if (skip) { skip = false; return; }
		document.body.append(loader);
	});
	window.addEventListener('pageshow', (e) => { if (e.persisted) loader.remove(); });
}

// navigator.clipboard exists only on https/localhost — fall back to execCommand on plain http
window.copyText = (text) => {
	if (navigator.clipboard && window.isSecureContext) return navigator.clipboard.writeText(text);
	const ta = Object.assign(document.createElement('textarea'), { value: text });
	ta.style.cssText = 'position:fixed;opacity:0';
	document.body.append(ta);
	ta.select();
	document.execCommand('copy');
	ta.remove();
};

function bootstrap() {
	window.renderIcons();
	initPageLoader();
	initFileDrops();
	initConfirmLinks();
	if (document.querySelector('textarea.tinymce')) {
		import('./tinymce-init.js')
			.then(({ initTinyMCE }) => initTinyMCE())
			.catch((err) => console.error('TinyMCE init failed:', err));
	}
	if (document.querySelector('[data-chart]')) {
		import('./charts-init.js')
			.then(({ initCharts }) => initCharts())
			.catch((err) => console.error('Charts init failed:', err));
	}
}

if (document.readyState === 'loading') {
	document.addEventListener('DOMContentLoaded', bootstrap);
} else {
	bootstrap();
}
