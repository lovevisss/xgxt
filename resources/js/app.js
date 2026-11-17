import { installMobileViewport } from './vue/utils/mobileViewport';
import './bootstrap';
import { createApp } from 'vue';
import '../css/app.css';
import AppRoot from './vue/AppRoot.vue';

const appEl = document.getElementById('app');

if (appEl) {
	installMobileViewport();
	let initialProps = {};
	let workspace = {};
	try {
		workspace = JSON.parse(appEl.dataset.workspace || '{}');
		initialProps = JSON.parse(appEl.dataset.props || '{}');
	} catch (error) {
		initialProps = {};
	}

	createApp(AppRoot, {
		page: appEl.dataset.page || '',
		initialProps,
		workspace,
	}).mount(appEl);
}
