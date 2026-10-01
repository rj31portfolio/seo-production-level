import Alpine from 'alpinejs';
import Chart from 'chart.js/auto';
document.querySelectorAll('[data-ranking-history]').forEach((canvas) => {
    const history = JSON.parse(canvas.dataset.rankingHistory);
    new Chart(canvas, { type: 'line', data: { labels: history.map(row => row.date), datasets: [{ label: 'Observed position', data: history.map(row => row.position), borderColor: '#ea580c', spanGaps: false }] }, options: { responsive: true, maintainAspectRatio: false, scales: { y: { reverse: true, min: 1 } } } });
});
window.Alpine = Alpine;
Alpine.start();
