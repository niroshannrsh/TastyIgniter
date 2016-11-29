<?php if (!defined('BASEPATH')) exit('No direct access allowed');

class Themes extends Admin_Controller
{

	public $create_url = 'themes/add';
	public $edit_url = 'themes/edit/{name}';
	public $delete_url = 'themes/delete/{name}';
	public $activate_url = 'themes/activate/{name}';
	protected $copy_url = 'themes/copy/{name}';

	public function __construct()
	{
		parent::__construct();

		$this->load->model('Themes_model');
		$this->load->model('Settings_model');
		$this->load->model('Image_tool_model');

		$this->load->helper('template');

		$this->lang->load('themes');
	}

	public function index()
	{
		$this->user->restrict('Site.Themes.Access');

		$this->template->setTitle($this->lang->line('text_title'));
		$this->template->setHeading($this->lang->line('text_heading'));
		$this->template->setButton($this->lang->line('button_new'), ['class' => 'btn btn-primary', 'href' => page_url() . '/add']);

		$data = $this->getList();

		$this->template->render('themes', $data);
	}

	public function edit()
	{
		$this->user->restrict('Site.Themes.Access');

		$theme_name = $this->uri->rsegment(3);
		if (!$theme_info = $this->Themes_model->getTheme($theme_name)) {
			$this->alert->set('danger', $this->lang->line('error_theme_not_found'));
			$this->redirect();
		}

		$this->load->library('customizer');
		$this->customizer->initialize($theme_info);

		if ($this->input->post() AND $this->_updateTheme($theme_info) === TRUE) {
			if ($this->input->post('save_close') == '1') {
				$this->redirect("themes/edit/{$theme_name}");
			}

			$this->redirect(current_url());
		}

		$this->template->setTitle(sprintf($this->lang->line('text_edit_heading'), $theme_info['title']));
		$this->template->setHeading(sprintf($this->lang->line('text_edit_heading'), $theme_info['title']));

		$this->template->setButton($this->lang->line('button_save'), ['class' => 'btn btn-primary', 'onclick' => '$(\'#edit-form\').submit();']);
		$this->template->setButton($this->lang->line('button_save_close'), ['class' => 'btn btn-default', 'onclick' => 'saveClose();']);
		$this->template->setButton($this->lang->line('button_icon_back'), ['class' => 'btn btn-default', 'href' => site_url('themes')]);

		$this->assets->setStyleTag(assets_url('js/colorpicker/css/bootstrap-colorpicker.min.css'), 'bootstrap-colorpicker-css');
		$this->assets->setStyleTag(assets_url('js/codemirror/codemirror.css'), 'codemirror-css');
		$this->assets->setScriptTag(assets_url('js/colorpicker/js/bootstrap-colorpicker.min.js'), 'bootstrap-colorpicker-js');
		$this->assets->setScriptTag(assets_url('js/codemirror/codemirror.js'), 'codemirror-js', '300');
		$this->assets->setScriptTag(assets_url('js/codemirror/xml/xml.js'), 'codemirror-xml-js', '301');
		$this->assets->setScriptTag(assets_url('js/codemirror/css/css.js'), 'codemirror-css-js', '302');
		$this->assets->setScriptTag(assets_url('js/codemirror/javascript/javascript.js'), 'codemirror-javascript-js', '303');
		$this->assets->setScriptTag(assets_url('js/codemirror/php/php.js'), 'codemirror-php-js', '304');
		$this->assets->setScriptTag(assets_url('js/codemirror/htmlmixed/htmlmixed.js'), 'codemirror-htmlmixed-js', '305');
		$this->assets->setScriptTag(assets_url('js/codemirror/clike/clike.js'), 'codemirror-clike-js', '306');
		$this->assets->setScriptTag(assets_url('js/jquery-sortable.js'), 'jquery-sortable-js');

		$data = $this->getForm($theme_info);

		$this->template->render('themes_edit', $data);
	}

	public function add()
	{
		$this->user->restrict('Site.Themes.Access');

		$this->template->setTitle($this->lang->line('text_add_heading'));
		$this->template->setHeading($this->lang->line('text_add_heading'));

		$this->template->setButton($this->lang->line('button_icon_back'), ['class' => 'btn btn-default', 'href' => site_url('themes')]);

		$data['_action'] = $this->pageUrl($this->create_url);

		if ($this->_addTheme() === TRUE) {
			$this->redirect();
		}

		$this->template->render('themes_add', $data);
	}

	public function activate()
	{
		$this->user->restrict('Site.Themes.Manage');

		if ($theme_name = $this->uri->rsegment(3)) {
			if ($theme_name = $this->Themes_model->activateTheme($theme_name)) {
				$this->alert->set('success', sprintf($this->lang->line('alert_success'), 'Theme [' . $theme_name . '] set as default '));
			}
		}

		$this->redirect();
	}

	public function copy()
	{
		$this->user->restrict('Site.Themes.Manage');

		$this->template->setTitle($this->lang->line('text_copy_heading'));
		$this->template->setHeading($this->lang->line('text_copy_heading'));

		$theme = $this->Themes_model->getTheme($this->uri->rsegment(3));

		if (!$this->uri->rsegment(3) OR empty($theme)) {
			$this->redirect(referrer_url());
		}

		$data['theme_title'] = $theme['title'];
		$data['theme_name'] = $theme['name'];
		$data['theme_data'] = !empty($theme['data']) ? TRUE : FALSE;
		$data['copy_action'] = !empty($theme['data']) ? $this->lang->line('text_files_data') : $this->lang->line('text_files');

		$data['files_to_copy'] = [];
		$files[] = load_theme_file('theme_config.php', $theme['name']);
		$files[] = load_theme_file('screenshot.png', $theme['name']);
		foreach ($files as $file) {
			$data['files_to_copy'][] = str_replace(ROOTPATH, '', $file['path']);
		}

		if ($this->input->post('confirm_copy') == $theme['name']) {
			$copy_data = ($this->input->post('copy_data') == '1') ? TRUE : FALSE;

			if ($this->Themes_model->copyTheme($theme['name'], $files, $copy_data)) {
				log_activity($this->user->getStaffId(), 'copied', 'themes', get_activity_message('activity_custom_no_link',
					['{staff}', '{action}', '{context}', '{item}'],
					[$this->user->getStaffName(), 'copied', 'theme', $data['theme_title']]
				));

				$this->alert->set('success', sprintf($this->lang->line('alert_success'), 'Theme [' . $theme['name'] . '] ' . $this->lang->line('text_copied')));
			} else {
				$this->alert->set('warning', $this->lang->line('alert_error_try_again'));
			}

			$this->redirect();
		}

		$this->template->render('themes_copy', $data);
	}

	public function delete()
	{
		$this->user->restrict('Site.Themes.Access');
		$this->user->restrict('Site.Themes.Delete');

		$this->template->setTitle($this->lang->line('text_delete_heading'));
		$this->template->setHeading($this->lang->line('text_delete_heading'));

		$theme = $this->Themes_model->getTheme($this->uri->rsegment(3));

		if (!$this->uri->rsegment(3) OR empty($theme)) {
			$this->redirect(referrer_url());
		} else if ($this->config->item(MAINDIR, 'default_themes') == $theme['name'] . '/') {
			$this->alert->set('warning', sprintf($this->lang->line('alert_error_nothing'), $this->lang->line('text_deleted') . $this->lang->line('text_theme_is_active')));
			$this->redirect(referrer_url());
		} else if ($this->config->item(MAINDIR . '_parent', 'default_themes') == $theme['name'] . '/') {
			$this->alert->set('warning', sprintf($this->lang->line('alert_error_nothing'), $this->lang->line('text_deleted') . $this->lang->line('text_theme_is_child_active')));
			$this->redirect(referrer_url());
		}

		$data['theme_title'] = $theme['title'];
		$data['theme_name'] = $theme['name'];
		$data['theme_data'] = !empty($theme['data']) ? TRUE : FALSE;
		$data['delete_action'] = !empty($theme['data']) ? $this->lang->line('text_files_data') : $this->lang->line('text_files');

		if ($this->input->post('confirm_delete') == $theme['name']) {
			$delete_data = ($this->input->post('delete_data') == '1') ? TRUE : FALSE;

			if ($this->Themes_model->deleteTheme($theme['name'], $delete_data)) {
				log_activity($this->user->getStaffId(), 'deleted', 'themes', get_activity_message('activity_custom_no_link',
					['{staff}', '{action}', '{context}', '{item}'],
					[$this->user->getStaffName(), 'deleted', 'theme', $data['theme_title']]
				));

				$this->alert->set('success', sprintf($this->lang->line('alert_success'), 'Theme [' . $theme['name'] . '] ' . $this->lang->line('text_deleted')));
			} else {
				$this->alert->set('warning', sprintf($this->lang->line('alert_error_nothing'), $this->lang->line('text_deleted')));
			}

			$this->redirect();
		}

		$data['files_to_delete'] = [];
		$files = find_theme_files($theme['name']);
		foreach ($files as $file) {
			$data['files_to_delete'][] = str_replace(ROOTPATH, '', $file['path']);
		}

		$this->template->render('themes_delete', $data);
	}

	public function getList()
	{
		$data['themes'] = [];
		$results = $this->Themes_model->paginateWithFilter();
		foreach ($results->list as $name => $theme) {
			if ($theme['name'] === trim($this->config->item(MAINDIR, 'default_themes'), '/')) {
				$active = '1';
			} else {
				$active = FALSE;
			}

			$data['themes'][$name] = array_merge($theme, [
				'parent_title' => (!empty($theme['parent']) AND !empty($themes[$theme['parent']]['title'])) ? $themes[$theme['parent']]['title'] : '',
				'active'       => $active,
				'activate'     => $this->pageUrl($this->activate_url, ['name' => $theme['name']]),
				'edit'         => $this->pageUrl($this->edit_url, ['name' => $theme['name']]),
				'delete'       => $this->pageUrl($this->delete_url, ['name' => $theme['name']]),
				'copy'         => $this->pageUrl($this->copy_url, ['name' => $theme['name']]),
			]);
		}

		$data['pagination'] = $results->pagination;

		return $data;
	}

	public function getForm($theme_info)
	{
		$url = '?';

		$_GET['extension_id'] = $theme_info['extension_id'];
		$theme_name = $theme_info['name'];
		$theme_config = (isset($theme_info['config'])) ? $theme_info['config'] : FALSE;

		$data['file'] = [];
		if ($this->input->get('file')) {
			$url .= 'file=' . $this->input->get('file');

			$theme_file = load_theme_file($this->input->get('file'), $theme_name);

			if (isset($theme_file['type']) AND $theme_file['type'] === 'img') {
				$theme_file['heading'] = sprintf($this->lang->line('text_viewing'), $this->input->get('file'), $theme_name);
			} else if (isset($theme_file['type']) AND $theme_file['type'] === 'file') {
				$theme_file['heading'] = sprintf($this->lang->line('text_editing'), $this->input->get('file'), $theme_name);
			} else {
				$this->alert->set('danger', $this->lang->line('error_file_not_supported'));
			}

			$data['file'] = $theme_file;
			$data['close_file'] = $this->pageUrl($this->edit_url, ['name' => $theme_name]);
		}

		$theme_files = '';
		$tree_link = $this->pageUrl($this->edit_url . '?file={link}', ['name' => $theme_name]);
		$theme_files .= $this->_themeTree($theme_name, $tree_link);

		$data['name'] = $theme_info['name'];
		$data['theme_files'] = $theme_files;
		$data['theme_config'] = $theme_config;
		$data['is_customizable'] = (isset($theme_info['customize']) AND $theme_info['customize']) ? TRUE : FALSE;

		$data['customizer_nav'] = $this->customizer->getNavView();
		$data['customizer_sections'] = $this->customizer->getSectionsView();
		$data['error_fields'] = [];

		if (!empty($data['is_customizable'])) {
			if (isset($theme_config['error_fields']) AND is_array($theme_config['error_fields'])) {
				foreach ($theme_config['error_fields'] as $error_field) {
					if (isset($error_field['field']) AND isset($error_field['error'])) {
						$data['error_fields'][$error_field['field']] = $error_field['error'];
					}
				}
			}
		}

		$data['_action'] = $this->pageUrl($this->edit_url . $url, ['name' => $theme_name]);

		$data['mode'] = '';
		if (!empty($data['file']['ext'])) {
			if ($data['file']['ext'] === 'php') {
				$data['mode'] = 'application/x-httpd-php';
			} else if ($data['file']['ext'] === 'css') {
				$data['mode'] = 'css';
			} else {
				$data['mode'] = 'javascript';
			}
		}

		return $data;
	}

	protected function _themeTree($directory, $return_link, $parent = '')
	{
		$current_path = ($this->input->get('file')) ? explode('/', $this->input->get('file')) : [];

		$theme_tree = '';
		$theme_tree .= ($parent === '') ? '<nav class="nav"><ul class="metisFolder">' : '<ul>';

		$theme_files = find_theme_files($directory);

		if (!empty($theme_files)) {
			foreach ($theme_files as $file) {
				$active = (in_array($file['name'], $current_path)) ? ' active' : '';

				if ($file['type'] === 'dir') {
					$parent_dir = $parent . '/' . $file['name'];
					$theme_tree .= '<li class="directory' . $active . '"><a><i class="fa fa-folder-open"></i>&nbsp;&nbsp;' . htmlspecialchars($file['name']) . '</a>';
					$theme_tree .= $this->_themeTree($directory . '/' . $file['name'], $return_link, $parent_dir);
					$theme_tree .= '</li>';
				} else if ($file['type'] === 'img') {
					$link = str_replace('{link}', $parent . '/' . urlencode($file['name']), $return_link);
					$theme_tree .= '<li class="img' . $active . '"><a href="' . $link . '"><i class="fa fa-file-image-o"></i>&nbsp;&nbsp;' . htmlspecialchars($file['name']) . '</a></li>';
				} else if ($file['type'] === 'file') {
					$link = str_replace('{link}', $parent . '/' . urlencode($file['name']), $return_link);
					$theme_tree .= '<li class="file' . $active . '"><a href="' . $link . '"><i class="fa fa-file-code-o"></i>&nbsp;&nbsp;' . htmlspecialchars($file['name']) . '</a></li>';
				}
			}
		}

		$theme_tree .= '</ul>';
		if ($parent === '') {
			$theme_tree .= '</nav>';
		}

		return $theme_tree;
	}

	protected function _addTheme()
	{
		if (isset($_FILES['theme_zip'])) {
			$this->user->restrict('Site.Themes.Add', site_url('themes/add'));

			if ($this->validateUpload() === TRUE) {
				$message = $this->Themes_model->extractTheme($_FILES['theme_zip']);

				if ($message === TRUE) {
					$theme_name = $_FILES['theme_zip']['name'];

					log_activity($this->user->getStaffId(), 'added', 'themes',
						get_activity_message('activity_custom_no_link',
							['{staff}', '{action}', '{context}', '{item}'],
							[$this->user->getStaffName(), 'added', 'a new theme', $theme_name]
						)
					);

					$this->alert->set('success', sprintf($this->lang->line('alert_success'), "Theme {$theme_name} added "));

					return TRUE;
				}

				$this->alert->danger_now(sprintf($this->lang->line('alert_error'), $message));
			}
		}

		return FALSE;
	}

	protected function _updateTheme($theme = [])
	{
		$this->user->restrict('Site.Themes.Manage');

		if ($this->uri->rsegment(3) AND $this->validateForm($theme['customize']) === TRUE) {
			if ($this->input->post('editor_area') AND $this->input->get('file')) {
				$theme_file = $this->input->get('file');
				if (save_theme_file($theme_file, $theme['name'], $this->input->post('editor_area'))) {
					$this->alert->set('success', sprintf($this->lang->line('alert_success'), "Theme File [{$theme_file}] saved "));
				} else {
					$this->alert->set('warning', sprintf($this->lang->line('alert_error_nothing'), 'saved'));
				}
			}

			$update['extension_id'] = $this->input->get('extension_id') ? $this->input->get('extension_id') : $theme['extension_id'];
			$update['name'] = $theme['name'];
			$update['title'] = $theme['title'];
			$update['version'] = $theme['version'];

			if (isset($theme['customize'])) {
				$update['data'] = $this->customizer->getPostData();
			}

			if ($this->Themes_model->updateTheme($update)) {
				$this->alert->set('success', sprintf($this->lang->line('alert_success'), 'Theme updated'));
			} else {
				$this->alert->set('warning', sprintf($this->lang->line('alert_error_nothing'), 'updated'));
			}

			return TRUE;
		}
	}

	protected function validateUpload()
	{
		if (!empty($_FILES['theme_zip']['name']) AND !empty($_FILES['theme_zip']['tmp_name'])) {

			if (preg_match('/\s/', $_FILES['theme_zip']['name'])) {
				$this->alert->danger_now($this->lang->line('error_upload_name'));

				return FALSE;
			}

			if ($_FILES['theme_zip']['type'] !== 'application/zip') {
				$this->alert->danger_now($this->lang->line('error_upload_type'));

				return FALSE;
			}

			$_FILES['theme_zip']['name'] = html_entity_decode($_FILES['theme_zip']['name'], ENT_QUOTES, 'UTF-8');
			$_FILES['theme_zip']['name'] = str_replace(['"', "'", "/", "\\"], "", $_FILES['theme_zip']['name']);
			$filename = $this->security->sanitize_filename($_FILES['theme_zip']['name']);
			$_FILES['theme_zip']['name'] = basename($filename, '.zip');

			if (!empty($_FILES['theme_zip']['error'])) {
				$this->alert->danger_now($this->lang->line('error_php_upload') . $_FILES['theme_zip']['error']);

				return FALSE;
			}

			if (file_exists(ROOTPATH . MAINDIR . '/views/themes/' . $_FILES['theme_zip']['name'])) {
				$this->alert->danger_now(sprintf($this->lang->line('alert_error'), $this->lang->line('error_theme_exists')));

				return FALSE;
			}

			if (is_uploaded_file($_FILES['theme_zip']['tmp_name'])) {
				return TRUE;
			}

			return FALSE;
		}
	}

	protected function validateForm($is_customizable = FALSE)
	{
		if ($is_customizable) {
			$rules = $this->customizer->getRules();
		}

		$rules[] = ['editor_area', 'Editor area'];

		$this->Themes_model;

		return $this->form_validation->set_rules($rules)->run();
	}
}

/* End of file Themes.php */
/* Location: ./admin/controllers/Themes.php */