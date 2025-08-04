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
	Indent,
	IndentBlock,
	Italic,
	Link,
	List,
	Paragraph,
	PasteFromOffice,
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
		Indent,
		IndentBlock,
		Italic,
		Link,
		List,
		Paragraph,
		PasteFromOffice,
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
	placeholder: 'Type or paste your content here!',
	translations: [translations]
};

const editors = {};

function initCKEditorForModal(modal) {
    const textarea = modal.querySelector('.ckeditor');
    if (!textarea || editors[textarea.id]) return;
    ClassicEditor.create(textarea, editorConfig)
        .then(editor => {
            editors[textarea.id] = editor;
        })
        .catch(console.error);
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.modal').forEach(modal => {
        modal.addEventListener('shown.bs.modal', () => {
            initCKEditorForModal(modal);
        });
    });
});
