import {
	ClassicEditor,
	Alignment,
	AutoLink,
	Autosave,
	BlockQuote,
	Bold,
	Essentials,
	GeneralHtmlSupport,
	Heading,
	Image,
	ImageCaption,
	ImageResize,
	ImageStyle,
	ImageToolbar,
	ImageInsert,
	ImageUpload,
	Indent,
	IndentBlock,
	Italic,
	Link,
	List,
	Paragraph,
	PasteFromOffice,
	SimpleUploadAdapter,
	SourceEditing,	
	Underline
} from 'ckeditor5';

import translations from 'ckeditor5/translations/fr.js';

/**
 * Create a free account with a trial: https://portal.ckeditor.com/checkout?plan=free
 */
const LICENSE_KEY = 'GPL'; // or <YOUR_LICENSE_KEY>.

//Configuration de ckeditor
const editorConfig = {
	toolbar: {
		items: [
			'undo',
			'redo',
			'|',
			'sourceEditing',
			'|',
			'heading',
			'|',
			'bold',
			'italic',
			'underline',
			'|',
			'link',
			'insertImage',
			'blockQuote',
			'|',
			'alignment',
			'|',
			'bulletedList',
			'numberedList',
			'outdent',
			'indent'
		],
		shouldNotGroupWhenFull: false
	},
	plugins: [
		Alignment,
		AutoLink,
		Autosave,
		BlockQuote,
		Bold,
		Essentials,
		GeneralHtmlSupport,
		Heading,
		Image,
		ImageCaption,
		ImageResize,
		ImageStyle,
		ImageToolbar,
		ImageInsert,
		ImageUpload,
		Indent,
		IndentBlock,
		Italic,
		Link,
		List,
		Paragraph,
		PasteFromOffice,
		SimpleUploadAdapter,
		SourceEditing,
		Underline
	],
	heading: {
		options: [
			{
				model: 'paragraph',
				title: 'Paragraph',
				class: 'ck-heading_paragraph'
			},
			{
				model: 'heading1',
				view: 'h1',
				title: 'Heading 1',
				class: 'ck-heading_heading1'
			},
			{
				model: 'heading2',
				view: 'h2',
				title: 'Heading 2',
				class: 'ck-heading_heading2'
			},
			{
				model: 'heading3',
				view: 'h3',
				title: 'Heading 3',
				class: 'ck-heading_heading3'
			},
			{
				model: 'heading4',
				view: 'h4',
				title: 'Heading 4',
				class: 'ck-heading_heading4'
			},
			{
				model: 'heading5',
				view: 'h5',
				title: 'Heading 5',
				class: 'ck-heading_heading5'
			},
			{
				model: 'heading6',
				view: 'h6',
				title: 'Heading 6',
				class: 'ck-heading_heading6'
			}
		]
	},
	htmlSupport: {
		allow: [
			{
				name: /^.*$/,
				styles: true,
				attributes: true,
				classes: true
			}
		]
	},
	licenseKey: LICENSE_KEY,
	link: {
		addTargetToExternalLinks: true,
		defaultProtocol: 'https://',
		decorators: {
			toggleDownloadable: {
				mode: 'manual',
				label: 'Downloadable',
				attributes: {
					download: 'file'
				}
			}
		}
	},
	image: {
		toolbar: [
			'imageTextAlternative',
			'toggleImageCaption',
			'|',
			'imageStyle:inline',
			'imageStyle:block',
			'imageStyle:side',
			'|',
			'linkImage'
		],
		resizeUnit: 'px'
	},
	placeholder: 'Type or paste your content here!',
	translations: [translations]
};

const editors = {};

function initCKEditorForModal(modal) {
	const textarea = modal.querySelector('.ckeditor, .ckeditor-textarea');
    if (!textarea || editors[textarea.id]) return;

	// Crée une nouvelle configuration pour cette instance de l'éditeur.
	// L'utilisation de `...editorConfig` permet de copier les propriétés
	// sans perdre les références aux classes des plugins, contrairement à JSON.parse(JSON.stringify()).
	const specificEditorConfig = {
		...editorConfig,
		extraPlugins: []
	};

	// Ajout d'un adaptateur d'upload personnalisé
	specificEditorConfig.extraPlugins.push(
		function(editor) {
			editor.plugins.get('FileRepository').createUploadAdapter = (loader) => new CustomUploadAdapter(loader, editor);
		}
	);

	ClassicEditor.create(textarea, specificEditorConfig)
		.then(editor => {
            editors[textarea.id] = editor;
        })
        .catch(console.error);
}

/**
 * Classe d'adaptateur d'upload personnalisée pour CKEditor.
 * Elle gère l'envoi du fichier au serveur en ajoutant des données dynamiques (comme displayUntil) à l'URL.
 */
class CustomUploadAdapter {
	constructor(loader, editor) {
		this.loader = loader;
		this.editor = editor;
	}

	upload() {
		return this.loader.file.then(file => new Promise((resolve, reject) => {
			const form = this.editor.sourceElement.closest('form');
			const displayUntilInput = form.querySelector('[name="display_until"]');
			const displayUntilValue = displayUntilInput ? displayUntilInput.value : '';

			const data = new FormData();
			data.append('upload', file);

			const xhr = new XMLHttpRequest();
			xhr.open('POST', `/gestion/accueil/upload?displayUntil=${encodeURIComponent(displayUntilValue)}`, true);
			xhr.responseType = 'json';

			xhr.addEventListener('load', () => {
				if (xhr.status === 200 && xhr.response && xhr.response.url) {
					resolve({ default: xhr.response.url });
				} else {
					reject(xhr.response.error?.message || 'Upload failed');
				}
			});

			xhr.addEventListener('error', () => reject('Network Error'));
			xhr.addEventListener('abort', () => reject('Upload aborted'));

			xhr.send(data);
		}));
	}

	abort() {
		// Cette méthode peut être implémentée pour gérer l'annulation de l'upload.
	}
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.modal').forEach(modal => {
        modal.addEventListener('shown.bs.modal', () => {
            initCKEditorForModal(modal);
        });
    });
});
