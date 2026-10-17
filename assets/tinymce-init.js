// Lazy chunk — loaded only on pages with .tinymce textareas
import tinymce from 'tinymce/tinymce';
import 'tinymce/models/dom';
import 'tinymce/themes/silver';
import 'tinymce/icons/default';
import 'tinymce/skins/ui/oxide/skin.css';
import 'tinymce/plugins/lists';
import 'tinymce/plugins/link';
import 'tinymce/plugins/image';
import 'tinymce/plugins/table';
import 'tinymce/plugins/code';
import 'tinymce/plugins/autolink';
import 'tinymce/plugins/searchreplace';
import 'tinymce/plugins/wordcount';

export function initTinyMCE() {
	tinymce.init({
		selector: 'textarea.tinymce',
		license_key: 'gpl',
		height: 400,
		menubar: false,
		plugins: 'lists link image table code autolink searchreplace wordcount',
		toolbar: 'undo redo | blocks | bold italic underline | bullist numlist | link image table | code',
		branding: false,
		promotion: false,
		skin: false,
		content_css: false,
	});
}
