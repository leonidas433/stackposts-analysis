import '../css/app.css';
import './parts/search-overlay';

// Alpine.js self-hosted (bundled from npm instead of the jsdelivr CDN).
// The Vite bundle is type="module", so it executes deferred — same timing
// as the previous <script defer> CDN tag.
import Alpine from 'alpinejs';

window.Alpine = Alpine;
Alpine.start();
