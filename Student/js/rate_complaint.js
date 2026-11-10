const speedInputs = document.querySelectorAll('input[name="speed"]');
const commInputs = document.querySelectorAll('input[name="communication"]');
const effInputs = document.querySelectorAll('input[name="effectiveness"]');
const speedValue = document.getElementById('speedValue');
const commValue = document.getElementById('commValue');
const effValue = document.getElementById('effValue');
const speedDesc = document.getElementById('speedDesc');
const commDesc = document.getElementById('commDesc');
const effDesc = document.getElementById('effDesc');

const descriptions = {
    5: { speed: 'Excellent - Very fast response', comm: 'Excellent - Clear and timely updates', eff: 'Perfect - Issue completely resolved' },
    4: { speed: 'Good - Quick response', comm: 'Good - Regular updates', eff: 'Good - Issue mostly resolved' },
    3: { speed: 'Average - Acceptable response time', comm: 'Average - Adequate communication', eff: 'Average - Issue partially resolved' },
    2: { speed: 'Poor - Slow response', comm: 'Poor - Limited communication', eff: 'Poor - Issue not fully resolved' },
    1: { speed: 'Very Poor - Extremely slow', comm: 'Very Poor - No communication', eff: 'Very Poor - Issue not resolved' }
};

function updateRatings() {
    let speed = document.querySelector('input[name="speed"]:checked')?.value || 3;
    speedValue.textContent = speed;
    speedDesc.textContent = descriptions[speed].speed;
    
    let comm = document.querySelector('input[name="communication"]:checked')?.value || 3;
    commValue.textContent = comm;
    commDesc.textContent = descriptions[comm].comm;
    
    let eff = document.querySelector('input[name="effectiveness"]:checked')?.value || 3;
    effValue.textContent = eff;
    effDesc.textContent = descriptions[eff].eff;
}

speedInputs.forEach(input => input.addEventListener('change', updateRatings));
commInputs.forEach(input => input.addEventListener('change', updateRatings));
effInputs.forEach(input => input.addEventListener('change', updateRatings));