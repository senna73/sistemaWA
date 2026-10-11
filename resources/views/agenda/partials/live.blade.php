<script>
    (function () {
        var toastTimer = 0;

        function csrfToken() {
            var meta = document.querySelector('meta[name="csrf-token"]');
            return meta ? meta.content : '';
        }

        function toast(message, ok) {
            var el = document.getElementById('agenda-toast');
            if (!el) {
                return;
            }
            el.classList.toggle('alert-success', ok);
            el.classList.toggle('alert-danger', !ok);
            el.textContent = message;
            el.hidden = false;
            window.clearTimeout(toastTimer);
            toastTimer = window.setTimeout(function () {
                el.hidden = true;
            }, 2800);
        }

        function captureScroll() {
            return [document.scrollingElement, document.querySelector('.content-wrapper'), document.querySelector('.layout-page')]
                .filter(Boolean)
                .map(function (node) {
                    return { node: node, top: node.scrollTop, left: node.scrollLeft };
                });
        }

        function restoreScroll(positions) {
            positions.forEach(function (position) {
                position.node.scrollTop = position.top;
                position.node.scrollLeft = position.left;
            });
        }

        function refreshAgenda() {
            var app = document.getElementById('agenda-app');
            var positions = captureScroll();
            return fetch(window.location.href, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-Agenda-Fragment': '1',
                    'Accept': 'text/html'
                },
                credentials: 'same-origin'
            }).then(function (response) {
                if (!response.ok) {
                    throw new Error('refresh');
                }
                return response.text();
            }).then(function (html) {
                app.innerHTML = html;
                restoreScroll(positions);
            });
        }

        function numberIn(box, name) {
            var el = box.querySelector('[data-agenda-' + name + ']');
            return el ? (parseInt(el.textContent, 10) || 0) : 0;
        }

        function writeNumber(box, name, value) {
            var el = box.querySelector('[data-agenda-' + name + ']');
            if (el) {
                el.textContent = String(value);
            }
        }

        function adjustProductivity(userId, receivedDelta, doneDelta, lateDelta) {
            if (!userId || (receivedDelta === 0 && doneDelta === 0 && lateDelta === 0)) {
                return;
            }
            var box = document.querySelector('[data-agenda-productivity="' + userId + '"]');
            if (!box) {
                return;
            }
            var received = Math.max(0, numberIn(box, 'received') + receivedDelta);
            var done = Math.max(0, numberIn(box, 'done') + doneDelta);
            var late = Math.max(0, numberIn(box, 'late') + lateDelta);
            writeNumber(box, 'received', received);
            writeNumber(box, 'done', done);
            writeNumber(box, 'late', late);
            var percent = box.querySelector('[data-agenda-percent]');
            if (percent) {
                percent.textContent = (received === 0 ? 0 : Math.round((done / received) * 100)) + '%';
            }
        }

        function share(status, late, assigneeId) {
            if (!assigneeId || status === 'cancelled') {
                return { id: assigneeId || '', received: 0, done: 0, late: 0 };
            }
            return {
                id: String(assigneeId),
                received: 1,
                done: status === 'done' ? 1 : 0,
                late: late && status !== 'done' ? 1 : 0
            };
        }

        function applyProductivity(previous, next) {
            if (previous.id === next.id) {
                adjustProductivity(previous.id, next.received - previous.received, next.done - previous.done, next.late - previous.late);
                return;
            }
            adjustProductivity(previous.id, -previous.received, -previous.done, -previous.late);
            adjustProductivity(next.id, next.received, next.done, next.late);
        }

        function bumpIndicator(key, delta) {
            if (!key || !delta) {
                return;
            }
            var el = document.querySelector('[data-agenda-indicator="' + key + '"]');
            if (!el) {
                return;
            }
            el.textContent = String(Math.max(0, (parseInt(el.textContent, 10) || 0) + delta));
        }

        function directorBucket(status, late) {
            if (status === 'cancelled') {
                return null;
            }
            if (status === 'done') {
                return 'finished';
            }
            if (late) {
                return 'late';
            }
            if (status === 'in_progress' || status === 'in_review') {
                return 'in_progress';
            }
            return 'pending';
        }

        function refreshColumn(column) {
            if (!column) {
                return;
            }
            var cards = column.querySelectorAll('[data-agenda-card]');
            var count = column.closest('.card');
            count = count ? count.querySelector('[data-agenda-count]') : null;
            if (count) {
                count.textContent = String(cards.length);
            }
            var empty = column.querySelector('[data-agenda-empty]');
            if (empty) {
                empty.hidden = cards.length > 0;
            }
        }

        function applyItem(card, item) {
            var onFunctionBoard = Boolean(card.closest('[data-agenda-column]'));
            var previousShare = share(card.dataset.status || '', card.dataset.late === '1', card.dataset.assigneeId || '');
            var nextShare = share(item.status, Boolean(item.late), item.assignee_id || '');
            if (onFunctionBoard) {
                var fromBucket = directorBucket(card.dataset.status || '', card.dataset.late === '1');
                var toBucket = directorBucket(item.status, Boolean(item.late));
                if (fromBucket !== toBucket) {
                    bumpIndicator(fromBucket, -1);
                    bumpIndicator(toBucket, 1);
                }
                applyProductivity(previousShare, nextShare);
            }

            var source = card.closest('[data-agenda-column], [data-agenda-classic-column]');
            if (item.remove) {
                card.remove();
                refreshColumn(source);
                return;
            }

            card.dataset.status = item.status;
            card.dataset.assigneeId = item.assignee_id || '';
            card.dataset.late = item.late ? '1' : '0';
            card.classList.toggle('border-danger', Boolean(item.late));

            var assignee = card.querySelector('[data-agenda-assignee]');
            if (assignee) {
                assignee.textContent = item.assignee_name || 'Fila do setor';
            }
            var label = card.querySelector('[data-agenda-status-label]');
            if (label && item.status_label) {
                label.textContent = item.status_label;
            }
            var note = card.querySelector('[data-agenda-note]');
            if (note) {
                var noteText = note.querySelector('[data-agenda-note-text]');
                if (noteText) {
                    noteText.textContent = item.correction_note || '';
                }
                note.hidden = !item.correction_note;
            }
            var lateBadge = card.querySelector('[data-agenda-late-badge]');
            if (lateBadge) {
                lateBadge.hidden = !item.late;
            }
            card.querySelectorAll('select[name="status"]').forEach(function (select) {
                select.value = item.status;
                select.dataset.prev = item.status;
            });
            card.querySelectorAll('select[name="assignee_id"]').forEach(function (select) {
                select.value = item.assignee_id || '';
                select.dataset.prev = select.value;
            });
            card.querySelectorAll('[data-agenda-form="card"] input[name="note"]').forEach(function (input) {
                input.value = '';
            });

            if (item.column) {
                var target = document.querySelector('[data-agenda-column="' + item.column + '"]');
                if (target && card.parentElement !== target) {
                    target.insertBefore(card, target.firstChild);
                    refreshColumn(source);
                    refreshColumn(target);
                }
            }
        }

        function errorMessage(data) {
            if (data && data.errors) {
                var first = Object.keys(data.errors)[0];
                if (first && data.errors[first] && data.errors[first][0]) {
                    return data.errors[first][0];
                }
            }
            return (data && data.message) || 'Não foi possível salvar.';
        }

        function sendAgenda(action, body, form) {
            if (form) {
                if (form.dataset.agendaBusy === '1') {
                    return Promise.resolve();
                }
                form.dataset.agendaBusy = '1';
                form.querySelectorAll('button, select, input').forEach(function (control) {
                    control.disabled = true;
                });
            }
            return fetch(action, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken()
                },
                body: body,
                credentials: 'same-origin'
            }).then(function (response) {
                return response.json().then(function (data) {
                    return { ok: response.ok, status: response.status, data: data };
                }).catch(function () {
                    return { ok: false, status: response.status, data: {} };
                });
            }).then(function (result) {
                if (!result.ok) {
                    toast(result.status === 419 ? 'A sessão expirou. Atualize a página.' : errorMessage(result.data), false);
                    if (form) {
                        form.querySelectorAll('select[data-prev]').forEach(function (select) {
                            select.value = select.dataset.prev;
                        });
                    }
                    return;
                }
                if (result.data.refresh) {
                    return refreshAgenda().then(function () {
                        toast(result.data.message || 'Atualizado.', true);
                    }).catch(function () {
                        toast('Salvo. Recarregue a página se a lista não mudar.', true);
                    });
                }
                if (result.data.item) {
                    document.querySelectorAll('[data-agenda-card="' + result.data.item.id + '"]').forEach(function (card) {
                        applyItem(card, result.data.item);
                    });
                }
                toast(result.data.message || 'Atualizado.', true);
            }).catch(function () {
                toast('Não foi possível salvar.', false);
            }).finally(function () {
                if (!form || !form.isConnected) {
                    return;
                }
                form.dataset.agendaBusy = '';
                form.querySelectorAll('button, select, input').forEach(function (control) {
                    control.disabled = false;
                });
            });
        }

        document.addEventListener('focusin', function (event) {
            var select = event.target;
            if (!(select instanceof HTMLSelectElement)) {
                return;
            }
            if (select.name === 'status' || select.name === 'assignee_id') {
                select.dataset.prev = select.value;
            }
        });

        document.addEventListener('change', function (event) {
            var select = event.target;
            if (!(select instanceof HTMLSelectElement) || select.name !== 'status' || !select.form) {
                return;
            }
            if (select.form.getAttribute('data-agenda-form') !== 'card') {
                return;
            }
            sendAgenda(select.form.action, new FormData(select.form), select.form);
        });

        document.addEventListener('submit', function (event) {
            var form = event.target;
            if (!(form instanceof HTMLFormElement) || !form.hasAttribute('data-agenda-form')) {
                return;
            }
            event.preventDefault();
            sendAgenda(form.action, new FormData(form), form);
        }, true);

        document.addEventListener('dragstart', function (event) {
            var card = event.target.closest('[data-agenda-card]');
            if (!card || event.target.closest('input, select, button, textarea, a')) {
                return;
            }
            event.dataTransfer.setData('text/plain', card.getAttribute('data-agenda-card'));
            event.dataTransfer.effectAllowed = 'move';
        });

        document.addEventListener('dragover', function (event) {
            if (event.target.closest('[data-agenda-column]')) {
                event.preventDefault();
            }
        });

        document.addEventListener('drop', function (event) {
            var column = event.target.closest('[data-agenda-column]');
            if (!column) {
                return;
            }
            event.preventDefault();
            var id = event.dataTransfer.getData('text/plain');
            var app = document.getElementById('agenda-app');
            var card = id ? document.querySelector('[data-agenda-card="' + id + '"]') : null;
            if (!id || !app || (card && card.closest('[data-agenda-column]') === column)) {
                return;
            }
            var body = new FormData();
            body.append('_token', csrfToken());
            body.append('status', column.getAttribute('data-status') || '');
            sendAgenda(app.dataset.statusUrl.replace('__ID__', id), body, null);
        });
    })();
</script>
