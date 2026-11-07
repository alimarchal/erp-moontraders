{{-- Live "is this customer code free?" check for any input marked data-code-check="<url>" (optional data-except="<ticket uuid>"). --}}
<script>
    document.querySelectorAll('input[data-code-check]').forEach((input) => {
        const status = document.createElement('p');
        status.setAttribute('aria-live', 'polite');
        status.style.cssText = 'margin:6px 0 0; font-size:13px; min-height:18px';
        input.insertAdjacentElement('afterend', status);
        let timer = null;
        let latest = 0;

        const check = () => {
            const code = input.value.trim();
            if (code === '') { status.textContent = ''; return; }
            const ticket = ++latest;
            const url = new URL(input.dataset.codeCheck, window.location.origin);
            url.searchParams.set('code', code);
            if (input.dataset.except) { url.searchParams.set('except', input.dataset.except); }
            status.style.color = '#64748b';
            status.textContent = 'Checking…';
            fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
                .then((response) => response.json())
                .then((result) => {
                    if (ticket !== latest) { return; }
                    status.style.color = result.available ? '#15803d' : '#b91c1c';
                    status.textContent = (result.available ? '✓ ' : '✗ ') + result.message;
                    input.setCustomValidity(result.available ? '' : result.message);
                })
                .catch(() => { if (ticket === latest) { status.textContent = ''; } });
        };

        input.addEventListener('input', () => { input.setCustomValidity(''); clearTimeout(timer); timer = setTimeout(check, 350); });
    });
</script>
