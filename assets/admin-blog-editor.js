document.querySelectorAll('[data-blog-editor]').forEach((editor) => {
  const canvas = editor.querySelector('[data-editor-canvas]');
  const output = editor.querySelector('[data-editor-output]');
  const form = editor.closest('form');
  const fileInput = editor.querySelector('[data-image-input]');
  const imageOptions = editor.querySelector('[data-image-options]');
  const uploadStatus = editor.querySelector('[data-upload-status]');
  const wordCount = editor.querySelector('[data-word-count]');
  const imageSize = editor.querySelector('[data-image-size]');
  const imageSizeOutput = editor.querySelector('[data-image-size-output]');
  const imageAlt = editor.querySelector('[data-image-alt]');
  const imageCaption = editor.querySelector('[data-image-caption]');
  let savedRange = null;
  let selectedFigure = null;

  const saveSelection = () => {
    const selection = window.getSelection();
    if (selection && selection.rangeCount && canvas.contains(selection.anchorNode)) savedRange = selection.getRangeAt(0).cloneRange();
  };

  const restoreSelection = () => {
    canvas.focus();
    if (!savedRange) return;
    const selection = window.getSelection();
    selection.removeAllRanges();
    selection.addRange(savedRange);
  };

  const insertImageBlock = (previewUrl, altText) => {
    const figure = document.createElement('figure');
    figure.className = 'image-center';
    figure.style.width = '70%';
    const image = document.createElement('img');
    image.src = previewUrl;
    image.alt = altText;
    const caption = document.createElement('figcaption');
    caption.textContent = 'Tulis keterangan foto';
    figure.append(image, caption);

    const paragraph = document.createElement('p');
    paragraph.append(document.createElement('br'));
    let anchor = null;
    if (savedRange) {
      const node = savedRange.startContainer.nodeType === Node.TEXT_NODE ? savedRange.startContainer.parentElement : savedRange.startContainer;
      anchor = node?.closest?.('p, h2, h3, blockquote, li, figure') || null;
      if (anchor?.matches('li')) anchor = anchor.closest('ul, ol');
      if (anchor && !canvas.contains(anchor)) anchor = null;
    }
    if (anchor) {
      anchor.after(figure, paragraph);
    } else {
      canvas.append(figure, paragraph);
    }

    const range = document.createRange();
    range.setStart(paragraph, 0);
    range.collapse(true);
    const selection = window.getSelection();
    selection.removeAllRanges();
    selection.addRange(range);
    savedRange = range.cloneRange();
    return figure;
  };

  const sync = () => {
    output.value = canvas.innerHTML.replaceAll('src="../uploads/', 'src="uploads/');
    const words = (canvas.innerText.match(/\S+/g) || []).length;
    wordCount.textContent = `${words} kata`;
  };

  const selectFigure = (figure) => {
    if (selectedFigure) selectedFigure.classList.remove('is-selected');
    selectedFigure = figure;
    imageOptions.hidden = !figure;
    if (!figure) return;
    figure.classList.add('is-selected');
    const width = Math.round(parseFloat(figure.style.width) || 70);
    imageSize.value = String(width);
    imageSizeOutput.textContent = `${width}%`;
    imageAlt.value = figure.querySelector('img')?.alt || '';
    imageCaption.value = figure.querySelector('figcaption')?.textContent || '';
  };

  editor.querySelectorAll('[data-command]').forEach((button) => button.addEventListener('click', () => {
    restoreSelection();
    document.execCommand(button.dataset.command, false);
    saveSelection();
    sync();
  }));

  editor.querySelector('[data-block-format]').addEventListener('change', (event) => {
    restoreSelection();
    document.execCommand('formatBlock', false, event.target.value);
    sync();
  });

  editor.querySelector('[data-font-family]').addEventListener('change', (event) => {
    restoreSelection();
    document.execCommand('fontName', false, event.target.value);
    saveSelection();
    sync();
  });

  editor.querySelector('[data-block]').addEventListener('click', (event) => {
    restoreSelection();
    document.execCommand('formatBlock', false, event.currentTarget.dataset.block);
    sync();
  });

  editor.querySelector('[data-link]').addEventListener('click', () => {
    saveSelection();
    const url = window.prompt('Masukkan alamat tautan (https://...)');
    if (!url) return;
    restoreSelection();
    document.execCommand('createLink', false, url);
    sync();
  });

  editor.querySelector('[data-image-trigger]').addEventListener('click', () => {
    saveSelection();
    fileInput.click();
  });

  fileInput.addEventListener('change', async () => {
    if (!fileInput.files.length) return;
    uploadStatus.textContent = 'Mengunggah dan mengoptimalkan foto…';
    const body = new FormData();
    body.append('_token', editor.dataset.csrf);
    body.append('image', fileInput.files[0]);
    try {
      const response = await fetch(editor.dataset.uploadEndpoint, { method: 'POST', body });
      const result = await response.json();
      if (!response.ok || !result.ok) throw new Error(result.message || 'Upload gagal.');
      const alt = fileInput.files[0].name.replace(/\.[^.]+$/, '');
      const figure = insertImageBlock(result.preview_url, alt.replace(/[&<>"']/g, ''));
      selectFigure(figure);
      uploadStatus.textContent = 'Foto berhasil disisipkan';
      sync();
    } catch (error) {
      uploadStatus.textContent = error.message;
    } finally {
      fileInput.value = '';
    }
  });

  editor.querySelectorAll('[data-image-align]').forEach((button) => button.addEventListener('click', () => {
    if (!selectedFigure) return;
    selectedFigure.className = `${button.dataset.imageAlign} is-selected`;
    sync();
  }));

  imageSize.addEventListener('input', () => {
    if (!selectedFigure) return;
    selectedFigure.style.width = `${imageSize.value}%`;
    imageSizeOutput.textContent = `${imageSize.value}%`;
    sync();
  });

  imageAlt.addEventListener('input', () => {
    const image = selectedFigure?.querySelector('img');
    if (image) image.alt = imageAlt.value;
    sync();
  });

  imageCaption.addEventListener('input', () => {
    if (!selectedFigure) return;
    let caption = selectedFigure.querySelector('figcaption');
    if (!caption) {
      caption = document.createElement('figcaption');
      selectedFigure.append(caption);
    }
    caption.textContent = imageCaption.value;
    sync();
  });

  editor.querySelector('[data-image-delete]').addEventListener('click', () => {
    if (!selectedFigure || !window.confirm('Hapus foto ini dari artikel?')) return;
    selectedFigure.remove();
    selectFigure(null);
    sync();
  });

  editor.querySelector('[data-image-options-close]').addEventListener('click', () => selectFigure(null));

  canvas.addEventListener('click', (event) => {
    const figure = event.target.closest('figure');
    if (figure) selectFigure(figure);
  });
  canvas.addEventListener('keyup', saveSelection);
  canvas.addEventListener('mouseup', saveSelection);
  canvas.addEventListener('input', sync);
  form.addEventListener('submit', sync);
  sync();
});
