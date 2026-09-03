import './bootstrap';
import './echo';
import './washing-machine';

// Alpine is bundled with and started by Livewire (@livewireScripts in the
// layout) as of Livewire v3+. Importing/starting a second copy here would
// create two competing Alpine instances.

import { Chart, registerables } from 'chart.js';
Chart.register(...registerables);
window.Chart = Chart;
