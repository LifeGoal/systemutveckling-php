document.querySelectorAll('[data-modal-open]').forEach((openButton) => {
	openButton.addEventListener('click', () => {
		const modal = document.getElementById(openButton.dataset.modalOpen);

		if (!modal) {
			return;
		}

		if (typeof modal.showModal === 'function') {
			modal.showModal();
		} else {
			modal.setAttribute('open', '');
		}
	});
});

document.querySelectorAll('[data-modal-close]').forEach((closeButton) => {
	closeButton.addEventListener('click', () => {
		const modal = document.getElementById(closeButton.dataset.modalClose);

		if (!modal) {
			return;
		}

		if (typeof modal.close === 'function') {
			modal.close();
		} else {
			modal.removeAttribute('open');
		}
	});
});

document.querySelectorAll('dialog').forEach((dialog) => {
	if (dialog.hasAttribute('open') && typeof dialog.showModal === 'function') {
		dialog.removeAttribute('open');
		dialog.showModal();
	}

	dialog.addEventListener('pointerdown', (event) => {
		if (event.target === dialog) {
			if (typeof dialog.close === 'function') {
				dialog.close();
			} else {
				dialog.removeAttribute('open');
			}
		}
	});
});

document.querySelectorAll('[data-expandable-description]').forEach((description) => {
	const text = description.querySelector('[data-description-text]') || description.querySelector('.group-description-text');
	const toggle = description.querySelector('[data-description-toggle]');

	if (!text || !toggle) return;

	if (text.scrollHeight > text.clientHeight) {
		description.classList.add('is-expandable');
	}

	toggle.addEventListener('click', () => {
		const isExpanded = description.classList.toggle('is-expanded');
		toggle.setAttribute('aria-expanded', String(isExpanded));
		toggle.textContent = isExpanded ? 'Visa mindre' : 'Läs mer...';
	});
});