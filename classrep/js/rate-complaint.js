
const descriptions = {
    5: { speed: 'Excellent - Very fast response', comm: 'Excellent - Clear and timely updates', eff: 'Perfect - Issue completely resolved' },
    4: { speed: 'Good - Quick response', comm: 'Good - Regular updates', eff: 'Good - Issue mostly resolved' },
    3: { speed: 'Average - Acceptable response time', comm: 'Average - Adequate communication', eff: 'Average - Issue partially resolved' },
    2: { speed: 'Poor - Slow response', comm: 'Poor - Limited communication', eff: 'Poor - Issue not fully resolved' },
    1: { speed: 'Very Poor - Extremely slow', comm: 'Very Poor - No communication', eff: 'Very Poor - Issue not resolved' }
};
function updateRatings() {
    let speed = document.querySelector('input[name="speed"]:checked')?.value || 3;
    let comm = document.querySelector('input[name="communication"]:checked')?.value || 3;
    let eff = document.querySelector('input[name="effectiveness"]:checked')?.value || 3;
    document.getElementById('speedValue').textContent = speed;
    document.getElementById('commValue').textContent = comm;
    document.getElementById('effValue').textContent = eff;
    document.getElementById('speedDesc').textContent = descriptions[speed].speed;
    document.getElementById('commDesc').textContent = descriptions[comm].comm;
    document.getElementById('effDesc').textContent = descriptions[eff].eff;
}
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('input[name="speed"]').forEach(i => i.addEventListener('change', updateRatings));
    document.querySelectorAll('input[name="communication"]').forEach(i => i.addEventListener('change', updateRatings));
    document.querySelectorAll('input[name="effectiveness"]').forEach(i => i.addEventListener('change', updateRatings));
    updateRatings();
});