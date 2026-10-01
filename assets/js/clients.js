document.addEventListener('DOMContentLoaded', function() {
    const addCarSwitch = document.getElementById('addCarSwitch');
    const carFields = document.getElementById('carFields');
    const noCarMessage = document.getElementById('noCarMessage');
    
    const makeSelect = document.getElementById('carMakeSelect');
    const modelSelect = document.getElementById('carModelSelect');
    const makeCustom = document.getElementById('carMakeCustom');
    const modelCustom = document.getElementById('carModelCustom');

    // Load makes
    fetch('/api/get_makes.php')
        .then(r => r.json())
        .then(makes => {
            makes.forEach(m => {
                const opt = document.createElement('option');
                opt.value = m.id;
                opt.textContent = m.name;
                makeSelect.appendChild(opt);
            });
            const otherOpt = document.createElement('option');
            otherOpt.value = 'other';
            otherOpt.textContent = '-- Інше / Вручну --';
            makeSelect.appendChild(otherOpt);
        });

    addCarSwitch.addEventListener('change', function() {
        if (this.checked) {
            carFields.style.display = 'block';
            noCarMessage.style.display = 'none';
            makeSelect.setAttribute('required', '');
        } else {
            carFields.style.display = 'none';
            noCarMessage.style.display = 'block';
            makeSelect.removeAttribute('required');
            modelSelect.removeAttribute('required');
        }
    });

    makeSelect.addEventListener('change', function() {
        const makeId = this.value;
        modelSelect.innerHTML = '<option value="">Оберіть модель</option>';
        
        if (makeId === 'other') {
            makeCustom.style.display = 'block';
            makeCustom.setAttribute('required', '');
            modelCustom.style.display = 'block';
            modelCustom.setAttribute('required', '');
            modelSelect.style.display = 'none';
            modelSelect.removeAttribute('required');
        } else if (makeId) {
            makeCustom.style.display = 'none';
            makeCustom.removeAttribute('required');
            modelSelect.style.display = 'block';
            modelSelect.removeAttribute('disabled');
            modelSelect.setAttribute('required', '');
            modelCustom.style.display = 'none';
            modelCustom.removeAttribute('required');

            fetch('/api/get_models.php?make_id=' + makeId)
                .then(r => r.json())
                .then(models => {
                    models.forEach(m => {
                        const opt = document.createElement('option');
                        opt.value = m.name; // Use name directly for car creation
                        opt.textContent = m.name;
                        modelSelect.appendChild(opt);
                    });
                    const otherOpt = document.createElement('option');
                    otherOpt.value = 'other';
                    otherOpt.textContent = '-- Інша модель --';
                    modelSelect.appendChild(otherOpt);
                });
        } else {
            modelSelect.setAttribute('disabled', '');
            makeCustom.style.display = 'none';
            modelCustom.style.display = 'none';
        }
    });

    modelSelect.addEventListener('change', function() {
        if (this.value === 'other') {
            modelCustom.style.display = 'block';
            modelCustom.setAttribute('required', '');
        } else {
            modelCustom.style.display = 'none';
            modelCustom.removeAttribute('required');
        }
    });
});
