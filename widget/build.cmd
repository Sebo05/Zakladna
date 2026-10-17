@echo off
rem Builds AnoWidget.exe with the C# compiler bundled in Windows (.NET Framework 4.8) - nothing to install.
cd /d "%~dp0"
"%WINDIR%\Microsoft.NET\Framework64\v4.0.30319\csc.exe" /nologo /codepage:65001 /target:winexe /optimize+ ^
	/out:AnoWidget.exe ^
	/r:System.Windows.Forms.dll /r:System.Drawing.dll /r:System.Security.dll /r:System.Web.Extensions.dll ^
	AnoWidget.cs
