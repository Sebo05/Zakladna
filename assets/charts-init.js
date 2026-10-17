// Lazy chunk — loads when any [data-chart] element exists
import { Chart, registerables } from 'chart.js';

Chart.register(...registerables);

function applyThemeDefaults() {
	const isDark = document.documentElement.classList.contains('dark');
	Chart.defaults.color = isDark ? '#98989d' : '#6e6e73';
	Chart.defaults.borderColor = isDark ? 'rgba(255,255,255,0.08)' : 'rgba(0,0,0,0.06)';
	Chart.defaults.font.family = "-apple-system, BlinkMacSystemFont, 'Inter', system-ui, sans-serif";
	Chart.defaults.scale.grid.color = isDark ? 'rgba(255,255,255,0.06)' : 'rgba(0,0,0,0.05)';
	Chart.defaults.scale.ticks.color = isDark ? '#98989d' : '#6e6e73';
	Chart.defaults.plugins.tooltip.backgroundColor = isDark ? '#2c2c2e' : '#1d1d1f';
	Chart.defaults.plugins.tooltip.cornerRadius = 10;
	Chart.defaults.plugins.tooltip.padding = 10;
}

function readJson(id) {
	const el = document.getElementById(id);
	if (!el) return null;
	try {
		return JSON.parse(el.textContent);
	} catch (err) {
		console.error(`Bad JSON in #${id}:`, err);
		return null;
	}
}

const instances = [];

export function initCharts() {
	applyThemeDefaults();

	const canvases = document.querySelectorAll('[data-chart][data-chart-config]');
	canvases.forEach((el) => {
		const key = el.dataset.chartConfig;
		const data = readJson(`chart-${key}`);
		const options = readJson(`chart-${key}-opts`) || {};
		if (data === null) return;
		try {
			instances.push(new Chart(el, { type: el.dataset.chart, data, options }));
		} catch (err) {
			console.error(`Chart "${key}" render failed:`, err);
		}
	});

	// Re-style charts when theme toggles
	const observer = new MutationObserver(() => {
		applyThemeDefaults();
		instances.forEach((c) => c.update());
	});
	observer.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
}
