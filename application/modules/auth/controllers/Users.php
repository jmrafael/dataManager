<?php
/**
 * Created by PhpStorm.
 * User: administrator
 * Date: 25/11/2018
 * Time: 10:43
 */

class Users extends MX_Controller
{
    private $controller;
    private $file_path;
    private $objPHPExcel;
    private $pdf;
    private $realm;

    private $data;

    public function __construct()
    {
        parent::__construct();
        $this->load->library(array('ion_auth', 'form_validation', 'user_agent', 'upload'));
        $this->load->helper(array('url', 'language'));
        $this->lang->load('auth');

        $this->form_validation->set_error_delimiters($this->config->item('error_start_delimiter', 'ion_auth'), $this->config->item('error_end_delimiter', 'ion_auth'));

        //variables
        $this->controller = $this->router->class;
        $this->realm = 'Authorized users of Sacids Openrosa';
  
        if (!$this->ion_auth->logged_in())
            redirect('auth/login', 'refresh');
    }

    //check if user allowed
    function has_allowed_perm($method_name)
    {
        if ($this->ion_auth->is_admin() || perms_role($this->controller, $method_name))
            return TRUE;
        else
            show_error("You are not allowed to view this page", 401, "Unauthorized");
    }

    //users lists
    function lists()
    {
        $this->data['title'] = 'Users';
        $this->has_allowed_perm($this->router->method);

        $this->data['users'] = $this->User_model->get_all();

        foreach ($this->data['users'] as $k => $user) {
            $this->data['users'][$k]->groups = $this->ion_auth->get_users_groups($user->user_id)->result();
        }

        //populate data
        $this->data['groups'] = $this->Group_model->get_all();

        //render view
        $this->load->view('header', $this->data);
        $this->load->view('users/lists');
        $this->load->view('footer');
    }

    // create a new user
    public function create()
    {
        $this->data['title'] = $this->lang->line('create_user_heading');
        $this->has_allowed_perm($this->router->method);

        $tables = $this->config->item('tables', 'ion_auth');

        $identity_column = $this->config->item('identity', 'ion_auth');
        $this->data['identity_column'] = $identity_column;

        // validate form input
        $this->form_validation->set_rules('first_name', $this->lang->line('create_user_validation_fname_label'), 'required');
        $this->form_validation->set_rules('last_name', $this->lang->line('create_user_validation_lname_label'), 'required');
        $this->form_validation->set_rules('email', $this->lang->line('create_user_validation_email_label'), 'valid_email');
        $this->form_validation->set_rules('phone', $this->lang->line('create_user_validation_phone_label'), 'required|callback_valid_phone|trim');
        $this->form_validation->set_rules('groups_ids[]', 'Group', 'required|trim');
        $this->form_validation->set_rules('identity', 'Username', 'required|callback_valid_user');
        $this->form_validation->set_rules('password', $this->lang->line('create_user_validation_password_label'), 'required|min_length[' . $this->config->item('min_password_length', 'ion_auth') . ']|matches[password_confirm]');
        $this->form_validation->set_rules('password_confirm', $this->lang->line('create_user_validation_password_confirm_label'), 'required');

        if ($this->form_validation->run($this) == true) {
            $email = strtolower($this->input->post('email'));
            $identity = ($identity_column === 'email') ? $email : $this->input->post('identity');
            $password = $this->input->post('password');
            $groups = array($this->input->post('groups_ids'));

            //digest password
            $digest_password = md5("{$identity}:{$this->realm}:{$password}");

            //additional data
            $additional_data = array(
                'first_name' => ucfirst($this->input->post('first_name')),
                'last_name' => ucfirst($this->input->post('last_name')),
                'phone' => $this->input->post('phone'),
                'digest_password' => $digest_password,
            );
        }
        if ($this->form_validation->run() == true && $id = $this->ion_auth->register(strtolower($identity), $password, $email, $additional_data, $groups)) {
            // redirect them back to the admin page
            $this->session->set_flashdata('message', display_message($this->ion_auth->messages()));
            redirect("auth/users/lists", 'refresh');
        } else {
            // display the create user form
            // set the flash data error message if there is one
            $this->data['message'] = (validation_errors() ? validation_errors() : ($this->ion_auth->errors() ? $this->ion_auth->errors() : $this->session->flashdata('message')));

            $this->data['first_name'] = array(
                'name' => 'first_name',
                'id' => 'first_name',
                'type' => 'text',
                'value' => $this->form_validation->set_value('first_name'),
                'class' => 'form-control',
                'placeholder' => 'Write first name...'
            );
            $this->data['last_name'] = array(
                'name' => 'last_name',
                'id' => 'last_name',
                'type' => 'text',
                'value' => $this->form_validation->set_value('last_name'),
                'class' => 'form-control',
                'placeholder' => 'Write last name...'
            );
            $this->data['identity'] = array(
                'name' => 'identity',
                'id' => 'identity',
                'type' => 'text',
                'value' => $this->form_validation->set_value('identity'),
                'class' => 'form-control',
                'placeholder' => 'Write username...'
            );
            $this->data['email'] = array(
                'name' => 'email',
                'id' => 'email',
                'type' => 'text',
                'value' => $this->form_validation->set_value('email'),
                'class' => 'form-control',
                'placeholder' => 'Write email address...'
            );

            $this->data['phone'] = array(
                'name' => 'phone',
                'id' => 'phone',
                'type' => 'text',
                'value' => $this->form_validation->set_value('phone'),
                'class' => 'form-control',
                'placeholder' => 'Write phone number...'
            );
            $this->data['password'] = array(
                'name' => 'password',
                'id' => 'password',
                'type' => 'password',
                'value' => $this->form_validation->set_value('password'),
                'class' => 'form-control',
                'placeholder' => 'Write password...'
            );
            $this->data['password_confirm'] = array(
                'name' => 'password_confirm',
                'id' => 'password_confirm',
                'type' => 'password',
                'value' => $this->form_validation->set_value('password_confirm'),
                'class' => 'form-control',
                'placeholder' => 'Confirm password...'
            );

            $this->data['groups'] = $this->Group_model->get_all();

            //render view
            $this->load->view('header', $this->data);
            $this->load->view('users/create');
            $this->load->view('footer');
        }
    }

    // edit a user
    public function edit($id)
    {
        $this->data['title'] = 'Edit User';
        $this->has_allowed_perm($this->router->method);

        $user = $this->ion_auth->user($id)->row();
        $currentGroups = $this->ion_auth->get_users_groups($id)->result();

        // validate form input
        $this->form_validation->set_rules('first_name', $this->lang->line('edit_user_validation_fname_label'), 'required');
        $this->form_validation->set_rules('last_name', $this->lang->line('edit_user_validation_lname_label'), 'required');
        $this->form_validation->set_rules('phone', $this->lang->line('edit_user_validation_phone_label'), 'required');
        $this->form_validation->set_rules('email', $this->lang->line('create_user_validation_email_label'), 'required|valid_email');
        $this->form_validation->set_rules('groups_ids[]', 'User Groups', 'required');
        $this->form_validation->set_rules('identity', 'Username', 'required');

        if (isset($_POST) && !empty($_POST)) {
            // do we have a valid request?
            if ($this->_valid_csrf_nonce() === FALSE || $id != $this->input->post('id')) {
                show_error($this->lang->line('error_csrf'));
            }

            // update the password if it was posted
            if ($this->input->post('password')) {
                $this->form_validation->set_rules('password', $this->lang->line('edit_user_validation_password_label'), 'required|min_length[' . $this->config->item('min_password_length', 'ion_auth') . ']|matches[password_confirm]');
                $this->form_validation->set_rules('password_confirm', $this->lang->line('edit_user_validation_password_confirm_label'), 'required');
            }

            if ($this->form_validation->run() === TRUE) {
                $identity = $this->input->post('identity');

                //update data
                $data = array(
                    'username' => strtolower($this->input->post('identity')),
                    'first_name' => ucfirst($this->input->post('first_name')),
                    'last_name' => ucfirst($this->input->post('last_name')),
                    'phone' => $this->input->post('phone'),
                    'email' => $this->input->post('email')
                );

                // update the password if it was posted
                if ($this->input->post('password')) {
                    $data['password'] = $this->input->post('password');
                    $password = $this->input->post('password');

                    //digest password
                    $data['digest_password'] = md5("{$identity}:{$this->realm}:{$password}");
                }

                // Only allow updating groups if user is admin
                if ($this->ion_auth->is_admin()) {
                    //Update the groups user belongs to
                    $groupData = $this->input->post('groups_ids');

                    if (isset($groupData) && !empty($groupData)) {

                        $this->ion_auth->remove_from_group('', $id);

                        foreach ($groupData as $grp) {
                            $this->ion_auth->add_to_group($grp, $id);
                        }
                    }
                }

                // check to see if we are updating the user
                if ($this->ion_auth->update($user->id, $data)) {
                    // redirect them back to the admin page if admin, or to the base url if non admin
                    $this->session->set_flashdata('message', display_message('User updated'));
                    redirect('auth/users/lists', 'refresh');

                } else {
                    // redirect them back to the admin page if admin, or to the base url if non admin
                    $this->session->set_flashdata('message', display_message('Failed to update user', 'danger'));
                    redirect('auth/users/edit/' . $id, 'refresh');
                }
            }
        }

        // display the edit user form
        $this->data['csrf'] = $this->_get_csrf_nonce();

        // set the flash data error message if there is one
        $this->data['message'] = (validation_errors() ? validation_errors() : ($this->ion_auth->errors() ? $this->ion_auth->errors() : $this->session->flashdata('message')));

        // pass the user to the view
        $this->data['user'] = $user;
        $this->data['currentGroups'] = $currentGroups;

        $this->data['first_name'] = array(
            'name' => 'first_name',
            'id' => 'first_name',
            'type' => 'text',
            'value' => $this->form_validation->set_value('first_name', $user->first_name),
            'class' => 'form-control'
        );
        $this->data['last_name'] = array(
            'name' => 'last_name',
            'id' => 'last_name',
            'type' => 'text',
            'value' => $this->form_validation->set_value('last_name', $user->last_name),
            'class' => 'form-control'
        );

        $this->data['identity'] = array(
            'name' => 'identity',
            'id' => 'identity',
            'type' => 'text',
            'value' => $this->form_validation->set_value('identity', $user->username),
            'class' => 'form-control'
        );
        $this->data['email'] = array(
            'name' => 'email',
            'id' => 'email',
            'type' => 'text',
            'value' => $this->form_validation->set_value('email', $user->email),
            'class' => 'form-control'
        );

        $this->data['phone'] = array(
            'name' => 'phone',
            'id' => 'phone',
            'type' => 'text',
            'value' => $this->form_validation->set_value('phone', $user->phone),
            'class' => 'form-control'
        );
        $this->data['password'] = array(
            'name' => 'password',
            'id' => 'password',
            'type' => 'password',
            'class' => 'form-control',
            'placeholder' => 'Write password...'
        );
        $this->data['password_confirm'] = array(
            'name' => 'password_confirm',
            'id' => 'password_confirm',
            'type' => 'password',
            'class' => 'form-control',
            'placeholder' => 'Confirm password...'
        );
        $this->data['groups'] = $this->Group_model->get_all();

        //render view
        $this->load->view('header', $this->data);
        $this->load->view('users/edit');
        $this->load->view('footer');
    }

    // activate the user
    function activate($id, $code = false)
    {
        $this->has_allowed_perm('activate');
        $activation = '';

        if ($code !== false) {
            $activation = $this->ion_auth->activate($id, $code);
        } else if ($this->ion_auth->is_admin()) {
            $activation = $this->ion_auth->activate($id);
        }

        if ($activation) {
            // redirect them to the auth page
            $this->session->set_flashdata('message', display_message($this->ion_auth->messages()));
            redirect("auth/users/lists", 'refresh');
        } else {
            // redirect them to the forgot password page
            $this->session->set_flashdata('message', display_message($this->ion_auth->errors(), 'danger'));
            redirect("auth/users/lists", 'refresh');
        }
    }

    // deactivate the user
    function deactivate($id = NULL)
    {
        $this->data['title'] = 'Deactivate User';
        $this->has_allowed_perm('activate');

        $id = (int)$id;

        $this->load->library('form_validation');
        $this->form_validation->set_rules('confirm', $this->lang->line('deactivate_validation_confirm_label'), 'required');
        $this->form_validation->set_rules('id', $this->lang->line('deactivate_validation_user_id_label'), 'required|alpha_numeric');

        if ($this->form_validation->run() == FALSE) {
            // insert csrf check
            $this->data['csrf'] = $this->_get_csrf_nonce();
            $user = $this->ion_auth->user($id)->row();
            $this->data['user'] = $user;

            //populate data
            $this->data['first_name'] = array(
                'name' => 'first_name',
                'id' => 'first_name',
                'type' => 'text',
                'value' => $this->form_validation->set_value('first_name', $user->first_name),
                'class' => 'form-control',
                'readonly' => ''
            );
            $this->data['last_name'] = array(
                'name' => 'last_name',
                'id' => 'last_name',
                'type' => 'text',
                'value' => $this->form_validation->set_value('last_name', $user->last_name),
                'class' => 'form-control',
                'readonly' => ''
            );

            //render view
            $this->load->view('header', $this->data);
            $this->load->view('users/deactivate');
            $this->load->view('footer');

        } else {
            // do we really want to deactivate?
            if ($this->input->post('confirm') == 'yes') {
                // do we have a valid request?
                if ($this->_valid_csrf_nonce() === FALSE || $id != $this->input->post('id')) {
                    show_error($this->lang->line('error_csrf'));
                }

                // do we have the right userlevel?
                if ($this->ion_auth->logged_in() && $this->ion_auth->is_admin()) {
                    $this->ion_auth->deactivate($id);
                    $this->session->set_flashdata('message', display_message('Account deactivated', 'danger'));
                }
            }

            // redirect them back to the auth page
            redirect('auth/users/lists', 'refresh');
        }
    }


    /*================================================================
    Callback function
    ===============================================================*/
    // check phone validity
    function valid_phone($str)
    {
        if ($str != "") {
            $str = str_replace(' ', '', trim($str));
            if (preg_match("/^[0-9]{10}$/", $str)) {
                return TRUE;
            } else {
                $this->form_validation->set_message('valid_phone', "The %s must contain 10 digit eg:0717705746");
                return FALSE;
            }
        }
    }

    //check existence of user
    function valid_user($str)
    {
        if ($str != "") {
            $str = str_replace(' ', '', trim($str));
            $user = $this->user_model->get_by(['username', $str]);

            if ($user) {
                $this->form_validation->set_message('valid_user', "%s already exist");
                return FALSE;
            } else {
                return TRUE;
            }
        }
    }


    public
    function _get_csrf_nonce()
    {
        $this->load->helper('string');
        $key = random_string('alnum', 8);
        $value = random_string('alnum', 20);
        $this->session->set_flashdata('csrfkey', $key);
        $this->session->set_flashdata('csrfvalue', $value);

        return array($key => $value);
    }

    public
    function _valid_csrf_nonce()
    {
        $csrfkey = $this->input->post($this->session->flashdata('csrfkey'));
        if ($csrfkey && $csrfkey == $this->session->flashdata('csrfvalue')) {
            return TRUE;
        } else {
            return FALSE;
        }
    }

    //function to upload excel user file
    function upload_users_file()
    {
        $config['upload_path'] = './assets/uploads/files/';
        $config['allowed_types'] = 'csv|xls|xlsx';
        $config['max_size'] = '20000';
        $config['overwrite'] = TRUE;
        $config['remove_spaces'] = TRUE;

        //initialize config
        $this->upload->initialize($config);

        if (isset($_FILES['attachment']) && !empty($_FILES['attachment']['name'])) {
            if ($this->upload->do_upload('attachment')) {
                // set a $_POST value for 'image' that we can use later
                $upload_data = $this->upload->data();

                //POST variables
                $_POST['attachment'] = $upload_data['file_name'];

                return true;
            } else {
                // possibly do some clean up ... then throw an error
                $this->form_validation->set_message('upload_users_file', $this->upload->display_errors());
                return false;
            }
        } else {
            // throw an error because nothing was uploaded
            $this->form_validation->set_message('upload_users_file', "Please, attach users file");
            return false;
        }
    }
}
