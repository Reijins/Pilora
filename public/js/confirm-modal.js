/**
 * Confirm Modal – composant réutilisable.
 *
 * Usage HTML :
 *   <form data-confirm="Message de confirmation"
 *         data-confirm-title="Titre"
 *         data-confirm-variant="danger|warning|default"
 *         data-confirm-btn="Texte bouton">
 *     ...
 *   </form>
 *
 * Ou sur un <button> / <a> qui doit soumettre un formulaire parent.
 */
(function () {
    'use strict';

    var overlay = null;
    var pendingForm = null;

    function getOrCreateOverlay() {
        if (overlay) return overlay;
        overlay = document.createElement('div');
        overlay.className = 'confirm-modal-overlay';
        overlay.setAttribute('role', 'dialog');
        overlay.setAttribute('aria-modal', 'true');
        overlay.innerHTML =
            '<div class="confirm-modal">' +
                '<div class="confirm-modal-header">' +
                    '<h4 class="confirm-modal-title" id="confirm-modal-title"></h4>' +
                    '<button type="button" class="confirm-modal-close" aria-label="Fermer">&times;</button>' +
                '</div>' +
                '<div class="confirm-modal-body" id="confirm-modal-body"></div>' +
                '<div class="confirm-modal-actions">' +
                    '<button type="button" class="btn btn-secondary" id="confirm-modal-cancel">Annuler</button>' +
                    '<button type="button" class="btn btn-danger" id="confirm-modal-ok">Confirmer</button>' +
                '</div>' +
            '</div>';
        document.body.appendChild(overlay);

        overlay.querySelector('.confirm-modal-close').addEventListener('click', closeModal);
        document.getElementById('confirm-modal-cancel').addEventListener('click', closeModal);
        document.getElementById('confirm-modal-ok').addEventListener('click', function () {
            if (pendingForm) {
                pendingForm.removeAttribute('data-confirm');
                pendingForm.requestSubmit ? pendingForm.requestSubmit() : pendingForm.submit();
            }
            closeModal();
        });
        overlay.addEventListener('click', function (e) {
            if (e.target === overlay) closeModal();
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && overlay.classList.contains('is-visible')) closeModal();
        });

        return overlay;
    }

    function openModal(form, message, title, variant, btnText) {
        var modal = getOrCreateOverlay();
        var modalBox = modal.querySelector('.confirm-modal');
        pendingForm = form;

        modalBox.className = 'confirm-modal';
        if (variant === 'danger') modalBox.classList.add('confirm-modal--danger');
        else if (variant === 'warning') modalBox.classList.add('confirm-modal--warning');

        document.getElementById('confirm-modal-title').textContent = title || 'Confirmation';
        document.getElementById('confirm-modal-body').textContent = message || 'Êtes-vous sûr ?';

        var okBtn = document.getElementById('confirm-modal-ok');
        okBtn.textContent = btnText || 'Confirmer';
        okBtn.className = 'btn';
        if (variant === 'danger') okBtn.classList.add('btn-danger');
        else if (variant === 'warning') okBtn.classList.add('btn-warning');
        else okBtn.classList.add('btn-primary');

        modal.classList.add('is-visible');
        okBtn.focus();
    }

    function closeModal() {
        if (overlay) overlay.classList.remove('is-visible');
        pendingForm = null;
    }

    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!form || !form.getAttribute) return;
        var msg = form.getAttribute('data-confirm');
        if (!msg) return;

        e.preventDefault();
        openModal(
            form,
            msg,
            form.getAttribute('data-confirm-title') || null,
            form.getAttribute('data-confirm-variant') || 'danger',
            form.getAttribute('data-confirm-btn') || null
        );
    });
})();
