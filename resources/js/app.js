import './bootstrap';
import { createApp } from 'vue';
import '../css/app.css';
import AppRoot from './vue/AppRoot.vue';

const appEl = document.getElementById('app');

if (appEl) {
	let initialProps = {};
	try {
		initialProps = JSON.parse(appEl.dataset.props || '{}');
	} catch (error) {
		initialProps = {};
	}

	createApp(AppRoot, {
		page: appEl.dataset.page || '',
		initialProps,
	}).mount(appEl);
}
