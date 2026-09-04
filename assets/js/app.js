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