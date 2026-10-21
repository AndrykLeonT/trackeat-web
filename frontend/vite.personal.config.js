import { defineConfig } from 'vite';
import { offlineConfig } from './offline-config.js';

export default defineConfig(offlineConfig('personal'));
