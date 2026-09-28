<script>
    document.querySelectorAll('.demo-overlay').forEach(function (overlay) {
        document.body.appendChild(overlay);
    });
    document.querySelectorAll('[data-open-demo-modal]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var overlay = document.getElementById(btn.getAttribute('data-open-demo-modal'));
            if (overlay) overlay.classList.add('is-open');
        });
    });
    document.querySelectorAll('.demo-overlay').forEach(function (overlay) {
        overlay.addEventListener('click', function (event) {
            if (event.target === overlay) overlay.classList.remove('is-open');
        });
    });
    document.querySelectorAll('[data-close-demo-modal]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            btn.closest('.demo-overlay')?.classList.remove('is-open');
        });
    });
    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Escape') return;
        var upload = document.getElementById('demo-upload-overlay');
        if (upload && upload.classList.contains('is-open')) return;
        document.querySelectorAll('.demo-overlay.is-open').forEach(function (overlay) {
            if (overlay.id === 'demo-upload-overlay') return;
            overlay.classList.remove('is-open');
        });
    });
    document.querySelectorAll('[data-store-filter]').forEach(function (input) {
        input.addEventListener('input', function () {
            var q = this.value.toLowerCase();
            this.closest('.store-board-wrap').querySelectorAll('.store-col').forEach(function (col) {
                var stageMatch = !q || (col.getAttribute('data-store') || '').indexOf(q) !== -1;
                var visible = 0;
                col.querySelectorAll('[data-card]').forEach(function (card) {
                    var show = stageMatch || (card.getAttribute('data-card') || '').indexOf(q) !== -1;
                    card.style.display = show ? '' : 'none';
                    if (show) visible += 1;
                });
                col.style.display = !q || visible > 0 ? '' : 'none';
            });
        });
    });

    document.querySelectorAll('[data-clinic-resolve]').forEach(function (form) {
        var error = form.querySelector('[data-clinic-error]');
        form.querySelectorAll('[data-clinic-choice]').forEach(function (box) {
            box.addEventListener('change', function () {
                if (!box.checked) {
                    return;
                }
                form.querySelectorAll('[data-clinic-choice]').forEach(function (other) {
                    if (other !== box) {
                        other.checked = false;
                    }
                });
                if (error) {
                    error.hidden = true;
                    error.textContent = '';
                }
            });
        });
        form.addEventListener('submit', function (event) {
            event.preventDefault();
            var chosen = form.querySelector('[data-clinic-choice]:checked');
            if (!chosen) {
                if (error) {
                    error.hidden = false;
                    error.textContent = 'Selecione a clínica.';
                }
                return;
            }
            var button = form.querySelector('[type=submit]');
            var token = document.querySelector('meta[name="csrf-token"]');
            if (button) {
                button.disabled = true;
            }
            fetch(form.action, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': token ? token.content : '',
                },
                body: JSON.stringify({ clinic_id: chosen.value }),
            }).then(function (response) {
                return response.json().then(function (data) {
                    return { ok: response.ok && data && data.ok === true, data: data };
                }).catch(function () {
                    return { ok: false, data: {} };
                });
            }).then(function (result) {
                if (!result.ok) {
                    if (button) {
                        button.disabled = false;
                    }
                    if (error) {
                        error.hidden = false;
                        error.textContent = (result.data && result.data.message) || 'Não foi possível regularizar.';
                    }
                    return;
                }
                var card = form.closest('[data-clinic-card]');
                if (card) {
                    card.remove();
                }
                var list = document.querySelector('[data-clinic-list]');
                var empty = document.querySelector('[data-clinic-empty]');
                if (empty && list && !list.querySelector('[data-clinic-card]')) {
                    empty.hidden = false;
                }
                var countEl = document.querySelector('[data-clinic-pending-count]');
                if (countEl) {
                    countEl.textContent = String(Math.max(0, parseInt(countEl.textContent, 10) - 1));
                }
            }).catch(function () {
                if (button) {
                    button.disabled = false;
                }
                if (error) {
                    error.hidden = false;
                    error.textContent = 'Não foi possível regularizar.';
                }
            });
        });
    });
</script>
