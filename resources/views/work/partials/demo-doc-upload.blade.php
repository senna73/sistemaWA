<div class="demo-overlay" id="demo-upload-overlay" role="dialog" aria-modal="true">
    <div class="demo-overlay-panel" style="width: min(480px, 100%);">
        <div class="demo-overlay-head">
            <h5 id="demo-upload-title">Enviar documento</h5>
            <button type="button" class="demo-overlay-close" data-close-upload aria-label="Fechar">&times;</button>
        </div>
        <div class="demo-overlay-body">
            <p class="text-muted" id="demo-upload-hint">Selecione uma imagem.</p>
            <div id="demo-upload-preview-wrap" class="text-center mb-3 d-none">
                <img id="demo-upload-preview" alt="Prévia" style="max-width: 100%; max-height: 280px; object-fit: contain;">
            </div>
            <input class="form-control" type="file" id="demo-upload-input" accept="image/*">
        </div>
    </div>
</div>
<script>
    (function () {
        var uploadOverlay = document.getElementById('demo-upload-overlay');
        if (! uploadOverlay) return;
        document.body.appendChild(uploadOverlay);

        var activeTile = null;
        var title = document.getElementById('demo-upload-title');
        var hint = document.getElementById('demo-upload-hint');
        var input = document.getElementById('demo-upload-input');
        var preview = document.getElementById('demo-upload-preview');
        var previewWrap = document.getElementById('demo-upload-preview-wrap');

        function closeUpload() {
            uploadOverlay.classList.remove('is-open');
            activeTile = null;
            input.value = '';
            previewWrap.classList.add('d-none');
        }

        function refreshDocs(root) {
            var tiles = Array.prototype.slice.call(root.querySelectorAll('[data-demo-doc]'));
            var next = tiles.findIndex(function (tile) { return tile.getAttribute('data-status') !== 'anexado'; });
            tiles.forEach(function (tile, index) {
                var done = tile.getAttribute('data-status') === 'anexado';
                var current = ! done && index === next;
                var previous = tile.getAttribute('data-previous');
                tile.classList.remove('anexado', 'pendente', 'atual', 'bloqueado');
                if (done) {
                    tile.classList.add('anexado');
                    return;
                }
                tile.classList.add('pendente');
                if (current) {
                    tile.classList.add('atual');
                    var span = tile.querySelector('span');
                    if (span) span.textContent = 'Clique para enviar a imagem agora.';
                } else {
                    tile.classList.add('bloqueado');
                    var locked = tile.querySelector('span');
                    if (locked) locked.textContent = 'Bloqueado até anexar ' + (previous || 'o documento anterior') + '.';
                }
            });
        }

        document.addEventListener('click', function (event) {
            var tile = event.target.closest('[data-demo-doc]');
            if (! tile) return;

            event.preventDefault();
            event.stopPropagation();

            if (tile.classList.contains('bloqueado')) {
                alert('Anexe primeiro: ' + (tile.getAttribute('data-previous') || 'o documento anterior') + '.');
                return;
            }

            activeTile = tile;
            title.textContent = tile.getAttribute('data-label');
            if (tile.getAttribute('data-status') === 'anexado') {
                hint.textContent = 'Documento já anexado.';
                input.classList.add('d-none');
                var src = tile.getAttribute('data-src') || (tile.querySelector('img') && tile.querySelector('img').getAttribute('src'));
                if (src) {
                    preview.src = src;
                    previewWrap.classList.remove('d-none');
                }
            } else {
                hint.textContent = 'Só este documento pode ser enviado agora.';
                input.classList.remove('d-none');
                previewWrap.classList.add('d-none');
            }
            uploadOverlay.classList.add('is-open');
        });

        input.addEventListener('change', function () {
            var file = input.files && input.files[0];
            if (! file || ! activeTile || activeTile.getAttribute('data-status') === 'anexado') return;
            if (! file.type.match(/^image\//)) {
                alert('Envie uma imagem.');
                return;
            }

            var uploadUrl = activeTile.getAttribute('data-upload-url');
            if (uploadUrl) {
                var token = document.querySelector('meta[name="csrf-token"]');
                var body = new FormData();
                body.append('file', file);
                body.append('kind', activeTile.getAttribute('data-kind') || 'letter');
                if (token) body.append('_token', token.getAttribute('content'));
                fetch(uploadUrl, {
                    method: 'POST',
                    body: body,
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                }).then(function (response) {
                    if (! response.ok) {
                        return response.json().then(function (payload) {
                            throw new Error((payload.message || (payload.errors && payload.errors.file && payload.errors.file[0]) || 'Não foi possível anexar.'));
                        }).catch(function (error) {
                            if (error instanceof Error && error.message) throw error;
                            throw new Error('Não foi possível anexar.');
                        });
                    }
                    window.location.reload();
                }).catch(function (error) {
                    alert(error.message || 'Não foi possível anexar.');
                });
                return;
            }

            var reader = new FileReader();
            reader.onload = function () {
                var src = reader.result;
                var stage = activeTile.getAttribute('data-stage') || '';
                var label = activeTile.getAttribute('data-label') || '';
                activeTile.setAttribute('data-status', 'anexado');
                activeTile.setAttribute('data-src', src);
                activeTile.classList.remove('pendente', 'atual', 'bloqueado');
                activeTile.classList.add('anexado');
                var strong = activeTile.querySelector('strong');
                activeTile.innerHTML = '';
                if (strong) activeTile.appendChild(strong);
                var img = document.createElement('img');
                img.src = src;
                img.alt = activeTile.getAttribute('data-label');
                activeTile.appendChild(img);
                var root = activeTile.closest('[data-demo-docs]');
                if (root) refreshDocs(root);
                if (stage === 'carta' || /carta/i.test(label)) {
                    advanceCard(activeTile);
                }
                closeUpload();
            };
            reader.readAsDataURL(file);
        });

        function advanceCard(tile) {
            var overlay = tile.closest('.demo-overlay');
            var slug = overlay && overlay.id ? overlay.id.replace('demo-card-', '') : '';
            var cardBtn = slug ? document.querySelector('[data-board-card="' + slug + '"]') : null;
            var col = cardBtn && cardBtn.closest('.store-col');
            var next = col && col.nextElementSibling;
            while (next && ! next.classList.contains('store-col')) {
                next = next.nextElementSibling;
            }
            if (cardBtn && next) {
                var body = next.querySelector('.store-col-body');
                if (body) body.appendChild(cardBtn);
            }
            if (overlay) overlay.classList.remove('is-open');
        }

        uploadOverlay.addEventListener('click', function (event) {
            if (event.target === uploadOverlay) closeUpload();
        });
        document.querySelectorAll('[data-close-upload]').forEach(function (btn) {
            btn.addEventListener('click', closeUpload);
        });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && uploadOverlay.classList.contains('is-open')) {
                event.stopPropagation();
                closeUpload();
            }
        });
    })();
</script>
