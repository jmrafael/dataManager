<?php
/**
 * AfyaData
 *
 * An open source data collection and analysis tool.
 *
 * This content is released under the MIT License (MIT)
 *
 * Copyright (c) 2016. Southern African Center for Infectious disease Surveillance (SACIDS)
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 *
 *
 * @package        AfyaData
 * @author        AfyaData Dev Team
 * @copyright    Copyright (c) 2016. Southen African Center for Infectious disease Surveillance (SACIDS
 *     http://sacids.org)
 * @license        http://opensource.org/licenses/MIT	MIT License
 * @link        https://afyadata.sacids.org
 * @since        Version 1.0.0
 */
defined('BASEPATH') or exit ('No direct script access allowed');
class XmlElement
{
    var $name;
    var $attributes;
    var $content;
    var $children;
}
/**
 * XForm Class
 *
 * @package  XForm
 * @category Controller
 * @author   Eric Beda
 * @link     http://sacids.org
 */
class Xform extends CI_Controller
{
    private $form_defn;
    private $form_data;
    private $xml_defn_filename;
    private $xml_data_filename;
    private $table_name;
    private $jr_form_id;
    private $xarray;
    private $user_id;
    private $user_submitting_feedback_id;
    private $sender; //Object
    public function __construct()
    {
        parent::__construct();
        $this->load->model(array(
            'Xform_model',
            'User_model',
            'Feedback_model',
            'Submission_model',
            'Ohkr_model',
            'Alert_model'
        ));
        $this->load->library('form_auth');
        $this->user_id = $this->session->userdata("user_id");
        $this->form_validation->set_error_delimiters('<div class="alert alert-danger">', '</div>');
    }
    public function index()
    {
        $this->forms();
    }
    /**
     *
     */
    function forms()
    {
        $this->_is_logged_in();
        $data['title'] = $this->lang->line("heading_form_list");
        if (!$this->input->post("search")) {
            $config = array(
                'base_url' => $this->config->base_url("xform/forms"),
                'total_rows' => $this->Xform_model->count_all_xforms("published"),
                'uri_segment' => 3,
            );
            $this->pagination->initialize($config);
            $page = ($this->uri->segment(3)) ? $this->uri->segment(3) : 0;
            if ($this->ion_auth->is_admin()) {
                $data['forms'] = $this->Xform_model->get_form_list(NULL, $this->pagination->per_page, $page, "published");
            } else {
                $data['forms'] = $this->Xform_model->find_forms_by_permission($this->user_id, $this->pagination->per_page, $page, "published");
            }
            $data["links"] = $this->pagination->create_links();
        } else {
            $form_name = $this->input->post("name", NULL);
            $access = $this->input->post("access", NULL);
            $status = $this->input->post("status", NULL);
            if ($this->ion_auth->is_admin()) {
                $forms = $this->Xform_model->search_forms(NULL, $form_name, $access, $status);
            } else {
                $forms = $this->Xform_model->search_forms($this->user_id, $form_name, $access, $status);
            }
            if ($forms) {
                $this->session->set_flashdata("message", display_message("Found " . count($forms) . " matching forms"));
                $data['forms'] = $forms;
            }
        }
        $this->load->view('header', $data);
        $this->load->view("form/index");
        $this->load->view('footer');
    }
    function _is_logged_in()
    {
        if (!$this->ion_auth->logged_in()) {
            redirect('auth/login', 'refresh');
        }
    }
    /**
     * XML submission function
     * Author : Renfrid
     *
     * @return response
     */
    public function submission()
    {
        // Form Received in openrosa server
        $http_response_code = 201;
        // Get the digest from the http header
        if (isset($_SERVER ['PHP_AUTH_DIGEST']))
            $digest = $_SERVER ['PHP_AUTH_DIGEST'];
        // server realm and unique id
        $realm = $this->config->item("realm");
        $nonce = md5(uniqid());
        // Check if there was no digest, show login
        if (empty ($digest)) {
            // populate login form if no digest authenticate
            $this->form_auth->require_login_prompt($realm, $nonce);
            log_message('debug', 'exiting, digest was not found');
            exit ();
        }
        // http_digest_parse
        $digest_parts = $this->form_auth->http_digest_parse($digest);
        // username obtained from http digest
        $username = $digest_parts ['username'];
        // get user details from database
        $user = $this->User_model->find_by_username($username);
        $this->sender = $user;
        $password = $user->digest_password; // digest password
        $db_username = $user->username; // username
        $this->user_submitting_feedback_id = $user->id;
        $uploaded_filename = NULL;
        // show status header if user not available in database
        if (empty ($db_username)) {
            // populate login form if no digest authenticate
            $this->form_auth->require_login_prompt($realm, $nonce);
            log_message('debug', 'username is not available');
            exit ();
        }
        // Based on all the info we gathered we can figure out what the response should be
        $A1 = $password; // digest password
        $A2 = md5("{$_SERVER['REQUEST_METHOD']}:{$digest_parts['uri']}");
        $calculated_response = md5("{$A1}:{$digest_parts['nonce']}:{$digest_parts['nc']}:{$digest_parts['cnonce']}:{$digest_parts['qop']}:{$A2}");
        // If digest fails, show login
        if ($digest_parts ['response'] != $calculated_response) {
            // populate login form if no digest authenticate
            $this->form_auth->require_login_prompt($realm, $nonce);
            log_message('debug', 'Digest does not match');
            exit ();
        }
        // IF passes authentication
        if ($_SERVER ['REQUEST_METHOD'] === "HEAD") {
            $http_response_code = 204;
        } elseif ($_SERVER ['REQUEST_METHOD'] === "POST") {
            foreach ($_FILES as $file) {
                // File details
                $file_name = $file ['name'];
                // check file extension
                $value = explode('.', $file_name);
                $file_extension = end($value);
                $inserted_form_id = NULL;
                if ($file_extension === 'xml') {
                    // path to store xml
                    $uploaded_filename = $file_name;
                    $path = $this->config->item("form_data_upload_dir") . $file_name;
                    // insert form details in database
                    $data = array(
                        'file_name' => $file_name,
                        'user_id' => $user->id,
                        "submitted_on" => date("Y-m-d h:i:s")
                    );
                    $inserted_form_id = $this->Submission_model->create($data);
                } elseif ($file_extension == 'jpg' or $file_extension == 'jpeg' or $file_extension == 'png') {
                    // path to store images
                    $path = $this->config->item("images_data_upload_dir") . $file_name;
                    //TODO Resize image here
                } elseif ($file_extension == '3gpp' or $file_extension == 'amr') {
                    // path to store audio
                    $path = $this->config->item("audio_data_upload_dir") . $file_name;
                } elseif ($file_extension == '3gp' or $file_extension == 'mp4') {
                    // path to store video
                    $path = $this->config->item("video_data_upload_dir") . $file_name;
                }
                // upload file to the server
                move_uploaded_file($file ['tmp_name'], $path);
            }
            // call function to insert xform data in a database
		log_message('DEBUG','Entering insert uploaded filename');
            if (!$this->_insert($uploaded_filename)) {
                if ($this->Submission_model->delete_submission($inserted_form_id))
                    @unlink($path);
		    exit();	
		// http response for failed insert
		$http_response_code = 401;
            }
        }
        // return response
        $this->get_response($http_response_code);
    }
    /**
     * inserts xform into database table
     * Author : Eric Beda
     *
     * @param string $filename
     * @return Mixed
     */

    public function insert_tmp(){
	echo $this->_insert('wardexamp.xml');
    }
    public function _insert($filename)
    {
        // call forms
        $this->set_data_file($this->config->item("form_data_upload_dir") . $filename);
        $this->load_xml_data();
	log_message('DEBUG','aftere load xml data');
        // get mysql statement used to insert form data into corresponding table
        $statement = $this->get_insert_form_data_query();

        $result = $this->Xform_model->insert_data($statement);
	log_message('DEBUG','XFORM INSERT DATA RESULT '.$result);

        if ($result) {
            $symptoms_reported = explode(" ", $this->form_data['Dalili_Dalili']);
            $district = $this->form_data['taarifa_awali_Wilaya']; // taarifa_awali_Wilaya is the database field name in the mean time
            if (count($symptoms_reported) > 0) {
                $message_sender_name = "AfyaData";
                $request_url_endpoint = "sms/1/text/single";
                $suspected_diseases_array = array();
                $suspected_diseases = $this->Ohkr_model->find_diseases_by_symptoms_code($symptoms_reported);
                $suspected_diseases_list = "Tumepokea fomu yako, kutokana na taarifa ulizotuma haya ndiyo magonjwa yanayodhaniwa ni:\n<br/>";
                if ($suspected_diseases) {
                    $i = 1;
                    foreach ($suspected_diseases as $disease) {
                        $suspected_diseases_list .= $i . "." . $disease->disease_name . "\n<br/>";
                        $suspected_diseases_array[$i - 1] = array(
                            "form_id" => $this->table_name,
                            "disease_id" => $disease->disease_id,
                            "instance_id" => $this->form_data['meta_instanceID'],
                            "date_detected" => date("Y-m-d H:i:s"),
                            "location" => $district
                        );
                        if (ENVIRONMENT == 'development') {
                            $sender_msg = $this->Ohkr_model->find_sender_response_message($disease->disease_id, "sender");
                            if ($sender_msg) {
                                $this->_save_msg_and_send($sender_msg->rsms_id, $this->sender->phone, $sender_msg->message,
                                    $this->sender->first_name, $message_sender_name, $request_url_endpoint);
                            }
                            $response_messages = $this->Ohkr_model->find_response_messages_and_groups($disease->disease_id,
                                $district);
                            $counter = 1;
                            if ($response_messages) {
                                foreach ($response_messages as $sms) {
                                    $this->_save_msg_and_send($sms->rsms_id, $sms->phone, $sms->message, $sms->first_name,
                                        $message_sender_name, $request_url_endpoint);
                                    $counter++;
                                }
                            }
                        }
                        $i++;
                    }
                    $this->Ohkr_model->save_detected_diseases($suspected_diseases_array);
                } else {
                    $suspected_diseases_list = "Hatukuweza kudhania ugonjwa kutokana na taarifa ulizotuma,
					tafadhali wasiliana na wataalam wetu kwa msaada zaidi";
                    log_message("debug", "Could not find disease with the specified symptoms");
                }
                $feedback = array(
                    "user_id" => $this->user_submitting_feedback_id,
                    "form_id" => $this->table_name,
                    "message" => $suspected_diseases_list,
                    "date_created" => date('Y-m-d H:i:s'),
                    "instance_id" => $this->form_data['meta_instanceID'],
                    "sender" => "server",
                    "status" => "pending"
                );
                $this->Feedback_model->create_feedback($feedback);
            } else {
                log_message("debug", "No symptom reported");
            }
        }
        return $result;
    }
    /**
     * @param $filename
     */
    public function set_data_file($filename)
    {
        $this->xml_data_filename = $filename;
    }
    /**
     * sets form_data variable to an array containing all fields of a filled xform file submitted
     * Author : Eric Beda
     */
    private function load_xml_data()
    {
        // get submitted file
        $file_name = $this->get_data_file();
        // load file into a string
        $xml = file_get_contents($file_name);
        // convert string into an object
        $rxml = $this->xml_to_object($xml);
        // array to hold values and field names;
        $this->form_data = array(); // TODO move to constructor
        $prefix = $this->config->item("xform_tables_prefix");
        //log_message("debug", "Table prefix " . $prefix);
        // set table name
        $this->table_name = $prefix . str_replace("-", "_", $rxml->attributes ['id']);
        // set form definition structure
        $file_name = $this->Xform_model->get_form_definition_filename($this->table_name);
        $this->set_defn_file($this->config->item("form_definition_upload_dir") . $file_name);
        $this->load_xml_definition();
        // set form data
        foreach ($rxml->children as $val) {
            $this->get_path('', $val);
        }
    }
    /**
     * @return mixed
     */
    public function get_data_file()
    {
        return $this->xml_data_filename;
    }
    /**
     * @param $xml
     * @return mixed
     */
    private function xml_to_object($xml)
    {
        $parser = xml_parser_create();
        xml_parser_set_option($parser, XML_OPTION_CASE_FOLDING, 0);
        xml_parser_set_option($parser, XML_OPTION_SKIP_WHITE, 1);
        xml_parse_into_struct($parser, $xml, $tags);
        xml_parser_free($parser);
        $elements = array(); // the currently filling [child] XmlElement array
        $stack = array();
        foreach ($tags as $tag) {
            $index = count($elements);
            if ($tag ['type'] == "complete" || $tag ['type'] == "open") {
                $elements [$index] = new XmlElement ();
                $elements [$index]->name = $tag ['tag'];
                if (!empty ($tag ['attributes'])) {
                    $elements [$index]->attributes = $tag ['attributes'];
                }
                if (!empty ($tag ['value'])) {
                    $elements [$index]->content = $tag ['value'];
                }
                if ($tag ['type'] == "open") { // push
                    $elements [$index]->children = array();
                    $stack [count($stack)] = &$elements;
                    $elements = &$elements [$index]->children;
                }
            }
            if ($tag ['type'] == "close") { // pop
                $elements = &$stack [count($stack) - 1];
                unset ($stack [count($stack) - 1]);
            }
        }
        return $elements [0]; // the single top-level element
    }
    /**
     * Recursive function that runs through xml xform object and uses array keys as
     * absolute path of variable, and sets its value to the data submitted by user
     * Author : Eric Beda
     *
     * @param string $name of xml element
     * @param object $obj
     */
    // TO DO : Change function name to be more representative
    /**
     * @param string $name
     * @param object $obj
     */
    private function get_path($name, $obj)
    {
        $name .= "_" . $obj->name;
        if (is_array($obj->children)) {
            foreach ($obj->children as $val) {
                $this->get_path($name, $val);
            }
        } else {
            $column_name = substr($name, 1);
            //shorten long column names
            if (strlen($column_name) > 64) {
                $column_name = shorten_column_name($column_name);
            }
            $this->form_data [$column_name] = $obj->content;
        }
    }
    /**
     * @param $response_msg_id
     * @param $phone
     * @param $message
     * @param $first_name
     * @param $message_sender_name
     * @param $request_url_endpoint
     * @internal param $sms
     */
    public function _save_msg_and_send($response_msg_id, $phone, $message, $first_name, $message_sender_name, $request_url_endpoint)
    {
        $sms_to_send = array(
            "response_msg_id" => $response_msg_id,
            "phone_number" => $phone,
            "date_sent" => date("Y-m-d h:i:s"),
            "status" => "PENDING"
        );
        if ($msg_id = $this->Ohkr_model->create_send_sms($sms_to_send)) {
            $sms_text = "Ndugu " . $first_name . ",\n" . $message;
            $sms_info = array(
                "from" => $message_sender_name,
                "to" => $phone,
                "text" => $sms_text
            );
            if ($send_result = $this->Alert_model->send_alert_sms($request_url_endpoint, $sms_info)) {
                $infobip_response = json_decode($send_result);
                $message = (array)$infobip_response->messages;
                $message = array_shift($message);
                $sms_updates = array(
                    "status" => "SENT",
                    "date_sent" => date("c"),
                    "infobip_msg_id" => $message->messageId,
                    "infobip_response" => $send_result
                );
                $this->Ohkr_model->update_sms_status($msg_id, $sms_updates);
            }
        }
    }
    /**
     * Create query string for inserting data into table from submitted xform data
     * file
     * Author : Eric Beda
     *
     * @return boolean|string
     */
    private function get_insert_form_data_query()
    {
        $table_name = $this->table_name;
        $form_data = $this->form_data;
        $map = $this->get_field_map();
        $has_geopoint = FALSE;
        $col_names = array();
        $col_values = array();
        $points_v = array();
        $points_n = array();
        //echo '<pre>'; print_r($this->form_defn);
        foreach ($this->form_defn as $str) {
            $type = $str['type'];
            $cn = $str['field_name'];
            $cv = $this->form_data[$cn];
            if ($cv == '' || $cn == '') continue;
            // check if column name was mapped to fieldmap table
            if (array_key_exists($cn, $map)) {
                $cn = $map[$cn];
            }
            array_push($col_names, $cn);
            array_push($col_values, $cv);
            if ($type == 'select') {
                $options = explode(' ', $cv);
                foreach ($options as $opt) {
                    $opt = trim($opt);
                    if (array_key_exists($opt, $map)) {
                        $opt = $map[$opt];
                    }
                    array_push($col_values, 1);
                    array_push($col_names, $opt);
                }
            }
            if ($type == 'geopoint') {
                $has_geopoint = TRUE;
                $geopoints = explode(" ", $cv);
                $lat = $geopoints [0];
                array_push($col_values, $lat);
                array_push($col_names, $cn . '_lat');
                $lng = $geopoints [1];
                array_push($col_values, $lng);
                array_push($col_names, $cn . '_lng');
                $alt = $geopoints [2];
                array_push($col_values, $alt);
                array_push($col_names, $cn . '_alt');
                $acc = $geopoints [3];
                array_push($col_values, $acc);
                array_push($col_names, $cn . '_acc');
                $point = "GeomFromText('POINT($lat $lng)')";
                array_push($points_v, $point);
                array_push($points_n, $cn . '_point');
            }
        }
        if ($has_geopoint) {
            $field_names = "(`" . implode("`,`", $col_names) . "`,`" . implode("`,`", $points_n) . "`)";
            $field_values = "('" . implode("','", $col_values) . "'," . implode("`,`", $points_v) . ")";
        } else {
            $field_names = "(`" . implode("`,`", $col_names) . "`)";
            $field_values = "('" . implode("','", $col_values) . "')";
        }
        $query = "INSERT INTO {$table_name} {$field_names} VALUES {$field_values}";
        return $query;
    }
    /**
     * Header Response
     *
     * @param string $http_response_code
     *            Input string
     * @param string $response_message
     *            Input string
     * @return response
     */
    function get_response($http_response_code, $response_message = "Thanks")
    {
        // OpenRosa Success Response
        $response = '<OpenRosaResponse xmlns="http://openrosa.org/http/response">
                    <message nature="submit_success">' . $response_message . '</message>
                    </OpenRosaResponse>';
        $content_length = sizeof($response);
        // set header response
        header("X-OpenRosa-Version: 1.0");
        header("X-OpenRosa-Accept-Content-Length:" . $content_length);
        header("Date: " . date('r'), FALSE, $http_response_code);
        echo $response;
    }
    /**
     * Handles authentication and lists forms for AfyaData app to consume.
     * it handles <base-url>/formList requests from ODK app.
     */
    function form_list()
    {
        // Get the digest from the http header
        if (isset($_SERVER['PHP_AUTH_DIGEST']))
            $digest = $_SERVER['PHP_AUTH_DIGEST'];
        //server realm and unique id
        $realm = $this->config->item("realm");
        $nonce = md5(uniqid());
        // If there was no digest, show login
        if (empty($digest)) {
            //populate login form if no digest authenticate
            $this->form_auth->require_login_prompt($realm, $nonce);
            exit;
        }
        //http_digest_parse
        $digest_parts = $this->form_auth->http_digest_parse($digest);
        //username from http digest obtained
        $username = $digest_parts['username'];
        //get user details from database
        $user = $this->User_model->find_by_username($username);
        $password = $user->digest_password; //digest password
        $db_user = $user->username; //username
        //show status header if user not available in database
        if (empty($db_user)) {
            //populate login form if no digest authenticate
            $this->form_auth->require_login_prompt($realm, $nonce);
            exit;
        }
        // Based on all the info we gathered we can figure out what the response should be
        $A1 = $password; //digest password
        $A2 = md5("{$_SERVER['REQUEST_METHOD']}:{$digest_parts['uri']}");
        $valid_response = md5("{$A1}:{$digest_parts['nonce']}:{$digest_parts['nc']}:{$digest_parts['cnonce']}:{$digest_parts['qop']}:{$A2}");
        // If digest fails, show login
        if ($digest_parts['response'] != $valid_response) {
            //populate login form if no digest authenticate
            $this->form_auth->require_login_prompt($realm, $nonce);
            exit;
        }
        $user_groups = $this->User_model->get_user_groups_by_id($user->id);
        $user_perms = array(0 => "P" . $user->id . "P");
        $i = 1;
        foreach ($user_groups as $ug) {
            $user_perms[$i] = "G" . $ug->id . "G";
            $i++;
        }
        $forms = $this->Xform_model->get_form_list_by_perms($user_perms);
        $xml = '<xforms xmlns="http://openrosa.org/xforms/xformsList">';
        foreach ($forms as $form) {
            // used to notify if anything has changed with the form, so that it may be updated on download
            $hash = md5($form->form_id . $form->date_created . $form->filename . $form->id . $form->title . $form->last_updated);
            $xml .= '<xform>';
            $xml .= '<formID>' . $form->form_id . '</formID>';
            $xml .= '<name>' . $form->title . '</name>';
            $xml .= '<version>1.1</version>';
            $xml .= '<hash>md5:' . $hash . '</hash>';
            $xml .= '<descriptionText>' . $form->description . '</descriptionText>';
            $xml .= '<downloadUrl>' . base_url() . 'assets/forms/definition/' . $form->filename . '</downloadUrl>';
            $xml .= '</xform>';
        }
        $xml .= '</xforms>';
        $content_length = sizeof($xml);
        //set header response
        header('Content-Type: text/xml; charset=utf-8');
        header('"HTTP_X_OPENROSA_VERSION": "1.0"');
        header("X-OpenRosa-Accept-Content-Length:" . $content_length);
        header('X-OpenRosa-Version:1.0');
        header("Date: " . date('r'), FALSE);
        echo $xml;
    }
    /**
     * Add/upload new xform and set permissions for groups or users.
     */
    function add_new()
    {
        if (!$this->ion_auth->logged_in()) {
            redirect('auth/login', 'refresh');
        }
        $data['title'] = $this->lang->line("heading_add_new_form");
        $this->form_validation->set_rules("title", $this->lang->line("validation_label_form_title"), "required|is_unique[xforms.title]");
        $this->form_validation->set_rules("access", $this->lang->line("validation_label_form_access"), "required");
        if ($this->form_validation->run() === FALSE) {
            $users = $this->User_model->get_users();
            $groups = $this->User_model->find_user_groups();
            $permission_options = array();
            foreach ($groups as $group) {
                $permission_options['G' . $group->id . 'G'] = $group->name;
            }
            foreach ($users as $user) {
                $permission_options['P' . $user->id . 'P'] = $user->first_name . " " . $user->last_name;
            }
            $data['perms'] = $permission_options;
            if ($this->input->is_ajax_request()) {
                $this->load->view("form/add_new", $data);
            } else {
                $this->load->view('header', $data);
                $this->load->view("form/add_new");
                $this->load->view('footer');
            }
        } else {
            $form_definition_upload_dir = $this->config->item("form_definition_upload_dir");
            //print_r($_FILES['userfile']['name']);
            if (!empty($_FILES['userfile']['name'])) {
                $config['upload_path'] = $form_definition_upload_dir;
                $config['allowed_types'] = 'xml';
                $config['max_size'] = '1024';
                $config['remove_spaces'] = TRUE;
                $this->load->library('upload', $config);
                $this->upload->initialize($config);
                if (!$this->upload->do_upload("userfile")) {
                    $this->session->set_flashdata("message", "<div class='warning'>" . $this->upload->display_errors("", "") . "</div>");
                    redirect("xform/add_new");
                } else {
                    $xml_data = $this->upload->data();
                    $filename = $xml_data['file_name'];
                    $perms = $this->input->post("perms");
                    $all_permissions = "";
                    if (count($perms) > 0) {
                        $all_permissions = join(",", $perms);
                    }
                    $create_table_statement = $this->_initialize($filename);
                    if ($this->Xform_model->find_by_xform_id($this->table_name)) {
                        @unlink($form_definition_upload_dir . $filename);
                        $this->session->set_flashdata("message", display_message("Form ID is already used, try a different one", "danger"));
                        redirect("xform/add_new");
                    } else {
                        $create_table_result = $this->Xform_model->create_table($create_table_statement);
                        //log_message("debug", "Create table result " . $create_table_result);
                        if ($create_table_result) {
                            $form_details = array(
                                "user_id" => $this->session->userdata("user_id"),
                                "form_id" => $this->table_name,
                                "jr_form_id" => $this->jr_form_id,
                                "title" => $this->input->post("title"),
                                "description" => $this->input->post("description"),
                                "filename" => $filename,
                                "date_created" => date("c"),
                                "access" => $this->input->post("access"),
                                "perms" => $all_permissions
                            );
                            //TODO Check if form is built from ODK Aggregate Build to avoid errors during initialization
                            if ($this->Xform_model->create_xform($form_details)) {
                                $this->session->set_flashdata("message", display_message($this->lang->line("form_upload_successful")));
                            } else {
                                $this->session->set_flashdata("message", display_message($this->lang->line("form_upload_failed"), "danger"));
                            }
                        } else {
                            $this->session->set_flashdata("message", display_message($create_table_statement, "danger"));
                        }
                        redirect("xform/add_new");
                    }
                }
            } else {
                $this->session->set_flashdata("message", display_message($this->lang->line("form_saving_failed"), "danger"));
                redirect("xform/add_new");
            }
        }
    }
    /**
     * Creates appropriate tables from an xform definition file
     * Author : Eric Beda
     *
     * @param string $file_name
     *            definition file
     * @return string with create table statement
     */
    public function _initialize($file_name)
    {
        //log_message("debug", "File to load " . $file_name);
        // create table structure
        $this->set_defn_file($this->config->item("form_definition_upload_dir") . $file_name);
        $this->load_xml_definition();
        // TODO: change function name to get_something suggested get_form_table_definition
        return $this->get_create_table_sql_query();
    }
    /**
     * @param $filename
     */
    public function set_defn_file($filename)
    {
        $this->xml_defn_filename = $filename;
    }
    /**
     * Create an array representative of xform definition file for easy transversing
     * Author : Eric Beda
     */
    private function load_xml_definition()
    {
        $file_name = $this->get_defn_file();
        $xml = file_get_contents($file_name);
        $rxml = $this->xml_to_object($xml);
        // TODO reference by names instead of integer keys
        $instance = $rxml->children [0]->children [1]->children [0]->children [0];
        $prefix = $this->config->item("xform_tables_prefix");
        //log_message("debug", "Table prefix during creation " . $prefix);
        $jr_form_id = $instance->attributes ['id'];
        $table_name = $prefix . str_replace("-", "_", $jr_form_id);
        // get array rep of xform
        $this->form_defn = $this->get_form_definition();
        //log_message("debug", "Table name " . $table_name);
        $this->table_name = $table_name;
        $this->jr_form_id = $jr_form_id;
    }
    /**
     * @return mixed
     */
    public function get_defn_file()
    {
        return $this->xml_defn_filename;
    }
    /**
     * Return a double array containing field path as key and a value containing
     * array filled with its corresponding attributes
     * Author : Eric Beda
     *
     * @return array
     */
    private function get_form_definition()
    {
        // retrieve object describing definition file
        $rxml = $this->xml_to_object(file_get_contents($this->get_defn_file()));
        // get the binds compononent of xform
        $binds = $rxml->children [0]->children [1]->children;
        //echo '<pre>'; print_r($rxml->children [1]->children);
        // get the body section of xform
        $tmp2 = $rxml->children [0]->children [1]->children [1]->children [0]->children;
        $tmp2 = $rxml->children [1]->children;
        // container
        $xarray = array();
        foreach ($binds as $key => $val) {
            if ($val->name == 'bind') {
                $attributes = $val->attributes;
                $nodeset = $attributes ['nodeset'];
                $xarray [$nodeset] = array();
                $xarray [$nodeset] ['field_name'] = str_replace("/", "_", substr($nodeset, 6));
                // set each attribute key and corresponding value
                foreach ($attributes as $k2 => $v2) {
                    $xarray [$nodeset] [$k2] = $v2;
                }
            }
        }
        $this->xarray = $xarray;
        $this->_iterate_defn_file($tmp2, FALSE);
        return $this->xarray;
    }
    /**
     * @param $arr
     * @param bool $ref
     */
    function _iterate_defn_file($arr, $ref = FALSE)
    {
        $i = 0;
        foreach ($arr as $val) {
            switch ($val->name) {
                case 'group':
                    $this->_iterate_defn_file($val->children);
                    break;
                case 'input':
                    $nodeset = $val->attributes['ref'];
                    $this->xarray[$nodeset]['label'] = '0';
                    break;
                case 'select':
                case 'select1':
                    $nodeset = $val->attributes['ref'];
                    $this->_iterate_defn_file($val->children, $nodeset);
                    break;
                case 'item':
                    $l = $val->children[0]->content;
                    $v = $val->children[1]->content;
                    $this->xarray[$ref]['option'][$v] = $l;
                    break;
            }
        }
    }
    /**
     * creates query corresponding to mysql table structure of an xform definition file
     * Author : Eric Beda
     *
     * @return string statement for creating table structure of xform
     */
    private function get_create_table_sql_query()
    {
        $structure = $this->form_defn;
        $tbl_name = $this->table_name;
        // initiate statement, set id as primary key, autoincrement
        $statement = "CREATE TABLE $tbl_name ( id INT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY ";
        // loop through xform definition array
        $counter = 0;
        foreach ($structure as $key => $val) {
            // check if type is empty
            if (empty ($val ['type']))
                continue;
            $field_name = $val['field_name'];
            $col_name = $this->_map_field($field_name);
            if (array_key_exists('label', $val)) {
                $field_label = $val['label'];
            } else {
                $tmp = explode('/', $val['nodeset']);
                $field_label = array_pop($tmp);
            }
            $type = $val ['type'];
            // check if field is required
            if (!empty ($val ['required'])) {
                $required = 'NOT NULL';
            } else {
                $required = '';
            }
            if ($type == 'string' || $type == 'binary' || $type = 'barcode') {
                $statement .= ", $col_name VARCHAR(300) $required";
            }
            if ($type == 'select1') {
                // Mysql recommended way of handling single quotes for queries is by using two single quotes at once.
                $tmp3 = array_keys($val ['option']);
                $statement .= ", $col_name ENUM('" . implode("','", str_replace("'", "''", $tmp3)) . "') $required";
            }
            if ($type == 'select') {
                $statement .= ", $col_name TEXT $required ";
                foreach ($val['option'] as $key => $select_opts) {
                    $key = $this->_map_field($key);
                    if (!$key) {
                        // failed need to exit
                    }
                    $statement .= ", " . $key . " ENUM('1','0') DEFAULT '0' NOT NULL ";
                }
            }
            if ($type == 'text') {
                $statement .= ", $col_name TEXT $required ";
            }
            if ($type == 'date') {
                $statement .= ", $col_name DATE $required ";
            }
            if ($type == 'dateTime') {
                $statement .= ", $col_name datetime $required";
            }
            if ($type == 'time') {
                $statement .= ", $col_name TIME $required";
            }
            if ($type == 'int') {
                $statement .= ", $col_name INT(20) $required ";
            }
            if ($type == 'decimal') {
                $statement .= ", $col_name DECIMAL $required ";
            }
            if ($type == 'geopoint') {
                $statement .= "," . $col_name . " VARCHAR(150) $required ";
                $statement .= "," . $col_name . "_point POINT $required ";
                $statement .= "," . $col_name . "_lat DECIMAL(38,10) $required ";
                $statement .= "," . $col_name . "_lng DECIMAL(38,10) $required ";
                $statement .= "," . $col_name . "_acc DECIMAL(38,10) $required ";
                $statement .= "," . $col_name . "_alt DECIMAL(38,10) $required ";
            }
            $statement .= "\n";
        }
        $statement .= ")";
        return $statement;
    }
    /**
     * @param $field_name
     * @return bool|string
     */
    private function _map_field($field_name)
    {
        // check length
        if (strlen($field_name) < 20) {
            return $field_name;
        }
        $tmp = sanitize_col_name($field_name);
        $asc = ascii_val($tmp);
        $fn = '_xf_' . condense_col_name($field_name) . '_' . $asc;
        $data = array();
        $data['table_name'] = $this->table_name;
        $data['col_name'] = $fn;
        $data['field_name'] = $field_name;
        if ($this->Xform_model->add_to_field_name_map($data)) {
            return $fn;
        }
        //log_message('error', 'failed to map field');
        return FALSE;
    }
    /**
     * @param $arr
     * @return bool|string
     */
    private function _add_to_fieldname_map($arr)
    {
        $ut = microtime();
        $pre = '';
        $prefix = explode("_", $field_name);
        foreach ($prefix as $parts) {
            $pre .= substr($value, 0, 1);
        }
        $pre = $pre . '_' . $ut;
        if ($this->Xform_model->set_field_name($pre, $field_name)) {
            return $pre;
        } else {
            return FALSE;
        }
    }
    /**
     * @return array of shortened field names mapped to xform xml file labels
     */
    private function get_field_map()
    {
        $arr = $this->Xform_model->get_fieldname_map($this->table_name);
        $map = array();
        foreach ($arr as $val) {
            $key = $val['field_name'];
            $label = $val['col_name'];
            $map[$key] = $label;
        }
        return $map;
    }
    /**
     * @param $xform_id
     */
    function edit_form($xform_id)
    {
        if (!$this->ion_auth->logged_in()) {
            redirect('auth/login', 'refresh');
        }
        if (!$xform_id) {
            $this->session->set_flashdata("message", $this->lang->line("select_form_to_edit"));
            redirect("xform/forms");
            exit;
        }
        $data['title'] = $this->lang->line("heading_edit_form");
        $data['form'] = $form = $this->Xform_model->find_by_id($xform_id);
        $this->form_validation->set_rules("title", $this->lang->line("validation_label_form_title"), "required");
        $this->form_validation->set_rules("access", $this->lang->line("validation_label_form_access"), "required");
        if ($this->form_validation->run() === FALSE) {
            $users = $this->User_model->get_users(200);
            $groups = $this->User_model->find_user_groups();
            $available_permissions = array();
            foreach ($groups as $group) {
                $available_permissions['G' . $group->id . 'G'] = $group->name;
            }
            foreach ($users as $user) {
                $available_permissions['P' . $user->id . 'P'] = $user->first_name . " " . $user->last_name;
            }
            $current_permissions = explode(",", $form->perms);
            $data['perms'] = $available_permissions;
            $data['current_perms'] = $current_permissions;
            $this->load->view('header', $data);
            $this->load->view("form/edit_form");
            $this->load->view('footer');
        } else {
            if ($form) {
                $new_perms = $this->input->post("perms");
                $new_perms_string = "";
                if (count($new_perms) > 0) {
                    $new_perms_string = join(",", $new_perms);
                }
                $new_form_details = array(
                    "title" => $this->input->post("title"),
                    "description" => $this->input->post("description"),
                    "access" => $this->input->post("access"),
                    "perms" => $new_perms_string,
                    "last_updated" => date("c")
                );
                if ($this->Xform_model->update_form($xform_id, $new_form_details)) {
                    $this->session->set_flashdata("message", display_message($this->lang->line("form_update_successful")));
                } else {
                    $this->session->set_flashdata("message", display_message($this->lang->line("form_update_failed"), "warning"));
                }
                redirect("xform/forms");
            } else {
                $this->session->set_flashdata("message", $this->lang->line("unknown_error_occurred"));
                redirect("xform/forms");
            }
        }
    }
    /**
     * @param $xform_id
     * Archives the uploaded xforms so that they do not appear at first on the form lists page
     */
    function archive_xform($xform_id)
    {
        if (!$this->ion_auth->logged_in()) {
            redirect('auth/login', 'refresh');
        }
        if (!$xform_id) {
            $this->session->set_flashdata("message", $this->lang->line("select_form_to_delete"));
            redirect("xform/forms");
            exit;
        }
        if ($this->Xform_model->archive_form($xform_id)) {
            $this->session->set_flashdata("message", display_message($this->lang->line("form_archived_successful")));
        } else {
            $this->session->set_flashdata("message", display_message($this->lang->line("error_failed_to_delete_form"), "danger"));
        }
        redirect("xform/forms");
    }
    /**
     * @param $xform_id
     */
    function restore_from_archive($xform_id)
    {
        if (!$this->ion_auth->logged_in()) {
            redirect('auth/login', 'refresh');
        }
        if (!$xform_id) {
            $this->session->set_flashdata("message", $this->lang->line("select_form_to_delete"));
            redirect("xform/forms");
            exit;
        }
        if ($this->Xform_model->restore_xform_from_archive($xform_id)) {
            $this->session->set_flashdata("message", display_message($this->lang->line("form_restored_successful")));
        } else {
            $this->session->set_flashdata("message", display_message($this->lang->line("error_failed_to_restore_form"), "danger"));
        }
        redirect("xform/forms");
    }
    /**
     * @param $form_id
     */
    function form_data($form_id)
    {
        $this->_is_logged_in();
        if (!$form_id) {
            $this->session->set_flashdata("message", $this->lang->line("select_form_to_delete"));
            redirect("xform/forms");
            exit;
        }
        $form = $this->Xform_model->find_by_id($form_id);
        if ($form) {
            // check if form_id ~ form data table is not empty or null
            $data['title'] = $form->title . " form";
            $data['form'] = $form;
            $data['table_fields'] = $this->Xform_model->find_table_columns($form->form_id);
            $data['field_maps'] = $this->_get_mapped_table_column_name($form->form_id);
            $data['mapped_fields'] = array();
            foreach ($data['table_fields'] as $key => $column) {
                if (array_key_exists($column, $data['field_maps'])) {
                    $data['mapped_fields'][$column] = $data['field_maps'][$column];
                } else {
                    $data['mapped_fields'][$column] = $column;
                }
            }
            $config = array(
                'base_url' => $this->config->base_url("xform/form_data/" . $form_id),
                'total_rows' => $this->Xform_model->count_all_records($form->form_id),
                'uri_segment' => 4,
            );
            $this->pagination->initialize($config);
            $page = ($this->uri->segment(4)) ? $this->uri->segment(4) : 0;
            $data['form_id'] = $form->form_id;
            //Prevents the filters from applying to a different form
            if ($this->session->userdata("filters_form_id") != $form_id) {
                $this->session->unset_userdata("filters_form_id");
                $this->session->unset_userdata("form_filters");
            }
            if (isset($_POST["apply"]) || $this->session->userdata("form_filters")) {
                if (!isset($_POST["apply"])) {
                    $selected_columns = $this->session->userdata("form_filters");
                } else {
                    $selected_columns = $_POST;
                    $selected_columns = array('id' => "ID") + $selected_columns;
                    unset($selected_columns['apply']);
                    $this->session->set_userdata("filters_form_id", $form_id);
                    $this->session->set_userdata(array("form_filters" => $selected_columns));
                }
                $data['selected_columns'] = $selected_columns;
                $data['form_data'] = $this->Xform_model->find_form_data_by_fields($form->form_id, $selected_columns, $this->pagination->per_page, $page);
            } else {
                $data['form_data'] = $this->Xform_model->find_form_data($form->form_id, $this->pagination->per_page, $page);
            }
            $data["links"] = $this->pagination->create_links();
            $this->load->view('header', $data);
            $this->load->view("form/form_data_details");
            $this->load->view('footer');
        } else {
            // form does not exist
        }
    }
    /**
     * Author: Renfrid
     * Export form data to excel
     *
     * @param null $form_id
     */
    function excel_export_form_data($form_id = NULL)
    {
        $this->load->library('excel');
        //get form data
        if ($this->session->userdata("form_filters")) {
            $form_filters = $this->session->userdata("form_filters");
            $serial = 0;
            foreach ($form_filters as $column_name) {
                $inc = 1;
                $column_title = $this->getColumnLetter($serial);
                $this->excel->setActiveSheetIndex(0)->setCellValue($column_title . $inc, $column_name);
                $serial++;
            }
            $form_data = $this->Xform_model->find_form_data_by_fields($form_id, $form_filters, 100000,0);
        } else {
            //table fields
            $table_fields = $this->Xform_model->find_table_columns($form_id);
            //mapping field
            $field_maps = $this->_get_mapped_table_column_name($form_id);
            $serial = 0;
            foreach ($table_fields as $key => $column) {
                $inc = 1;
                $column_title = $this->getColumnLetter($serial);
                if (array_key_exists($column, $field_maps)) {
                    $column_name = $field_maps[$column];
                } else {
                    $column_name = $column;
                }
                $this->excel->setActiveSheetIndex(0)->setCellValue($column_title . $inc, $column_name);
                $serial++;
            }
            $form_data = $this->Xform_model->find_form_data($form_id, 100000,0);
        }
        $inc = 2;
        foreach ($form_data as $data) {
            $serial = 0;
            foreach ($data as $key => $entry) {
                $column_title = $this->getColumnLetter($serial);
                if (preg_match('/(\.jpg|\.png|\.bmp)$/', $entry)) {
                    $column_value = '';
                } else {
                    $column_value = $entry;
                }
                $this->excel->getActiveSheet()->setCellValue($column_title . $inc, $column_value);
                $serial++;
            }
            $inc++;
        }
        //name the worksheet
        $this->excel->getActiveSheet()->setTitle("Form Data");
        $filename = "Exported_" . $form_id . "_" . date("Y-m-d") . ".xlsx"; //save our workbook as this file name
        //header
        header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
        header("Cache-Control: no-store, no-cache, must-revalidate");
        header("Cache-Control: post-check=0, pre-check=0", FALSE);
        header("Pragma: no-cache");
        header('Content-type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        $objWriter = PHPExcel_IOFactory::createWriter($this->excel, 'Excel2007');
        ob_end_clean();
        $objWriter->save('php://output');
    }
    /**
     * @param null $xform_id
     */
    function csv_export_form_data($xform_id = NULL)
    {
        if ($xform_id == NULL) {
            $this->session->set_flashdata("message", display_message("You must select a form", "danger"));
            redirect("xform/forms");
        }
        $table_name = $xform_id;
        $query = $this->db->query("select * from {$table_name} order by id ASC ");
        $this->_force_csv_download($query, "Exported_CSV_for_" . $table_name . "_" . date("Y-m-d") . ".csv");
    }
    /**
     * @param $query
     * @param string $filename
     */
    function _force_csv_download($query, $filename = '.csv')
    {
        $this->load->dbutil();
        $this->load->helper('file');
        $this->load->helper('download');
        $delimiter = ",";
        $newline = "\r\n";
        $data = $this->dbutil->csv_from_result($query, $delimiter, $newline);
        force_download($filename, $data);
    }
    /**
     * @param null $xform_id
     */
    function xml_export_form_data($xform_id = NULL)
    {
        if ($xform_id == NULL) {
            $this->session->set_flashdata("message", display_message("You must select a form", "danger"));
            redirect("xform/forms");
        }
        $table_name = $xform_id;
        $query = $this->db->query("select * from {$table_name} order by id ASC ");
        $this->_force_xml_download($query, "Exported_CSV_for_" . $table_name . "_" . date("Y-m-d") . ".xml");
    }
    /**
     * @param $query
     * @param string $filename
     */
    function _force_xml_download($query, $filename = '.xml')
    {
        $this->load->dbutil();
        $this->load->helper('file');
        $this->load->helper('download');
        $config = array(
            'root' => 'afyadata',
            'element' => 'form_data',
            'newline' => "\n",
            'tab' => "\t"
        );
        $data = $this->dbutil->xml_from_result($query, $config);
        force_download($filename, $data);
    }
    /**
     * @param $form_id
     */
    function map_fields($form_id)
    {
        if (!$form_id) {
            $this->session->set_flashdata("message", display_message("You must select a form", "danger"));
            redirect("xform/forms");
        }
        $this->form_validation->set_rules("save", "Save changes", "required");
        if ($this->form_validation->run() == FALSE) {
            $data['form_id'] = $form_id;
            $data['field_maps'] = $field_maps = $this->Xform_model->get_fieldname_map($form_id);
            $this->load->view('header', $data);
            $this->load->view("form/map_form_fields");
            $this->load->view('footer');
        } else {
            $fields = $this->input->post();
            unset($fields['save']);
            $this->Xform_model->update_field_map_labels($form_id, $fields);
            $this->session->set_flashdata("message", display_message("Field mapping successfully updated"));
            redirect("xform/map_fields/" . $form_id, "refresh");
        }
    }
    /**
     * @param $form_id
     * @return array
     */
    function _get_mapped_table_column_name($form_id)
    {
        if (!$form_id)
            $form_id = "ad_build_week_report_skolls_b_1767716170";
        $this->table_name = $form_id;
        $map = $this->get_field_map();
        //print_r($map);
        $this->load->library("Xform_comm");
        $form_details = $this->Feedback_model->get_form_details($form_id);
        $file_name = $form_details->filename;
        $this->xform_comm->set_defn_file($this->config->item("form_definition_upload_dir") . $file_name);
        $this->xform_comm->load_xml_definition($this->config->item("xform_tables_prefix"));
        $form_definition = $this->xform_comm->get_defn();
        $table_field_names = array();
        foreach ($form_definition as $fdfn) {
            $kk = $fdfn['field_name'];
            // check if column name was mapped to fieldmap table
            if (array_key_exists($kk, $map)) {
                $kk = $map[$kk];
            }
            if (array_key_exists("label", $fdfn)) {
                if ($fdfn['type'] == "select") {
                    $options = $fdfn['option'];
                    foreach ($options as $key => $value) {
                        // check if column name was mapped to fieldmap table
                        if (array_key_exists($key, $map)) {
                            $key = $map[$key];
                        }
                        $table_field_names[$key] = $value;
                    }
                } elseif ($fdfn['type'] == "int") {
                    $find_male = " m ";
                    $find_female = " f ";
                    $group_name = str_replace("_", " ", $fdfn['field_name']);
                    if (strpos($group_name, $find_male)) {
                        $table_field_names[$kk] = str_replace($find_male, " " . $fdfn['label'] . " ", $group_name);
                    } elseif (strpos($group_name, $find_female)) {
                        $table_field_names[$kk] = str_replace($find_female, " " . $fdfn['label'] . " ", $group_name);
                    } else {
                        $table_field_names[$kk] = $group_name . " " . $fdfn['label'];
                    }
                } else {
                    $table_field_names[$kk] = $fdfn['label'];
                }
            } else {
                $table_field_names[$kk] = $fdfn['field_name'];
            }
        }
        return $table_field_names;
    }
    /**
     * @param $xform_id
     * Deletes as single or multiple entries for a given form table and id(s)
     */
    function delete_entry($xform_id)
    {
        $this->form_validation->set_rules("entry_id[]", "Entry ID", "required");
        if ($this->form_validation->run() === FALSE) {
            $this->form_data($xform_id);
        } else {
            $table_name = $this->input->post("table_name");
            $entry_ids = $this->input->post("entry_id");
            $deleted_entry_count = 0;
            foreach ($entry_ids as $entry_id) {
                //TODO Implement delete media files too.
                if ($this->Xform_model->delete_form_data($table_name, $entry_id)) {
                    $deleted_entry_count++;
                }
            }
            $message = ($deleted_entry_count == 1) ? "entry" : "entries";
            $this->session->set_flashdata("message", display_message($deleted_entry_count . " " . $message . " deleted successfully"));
            redirect("xform/form_data/" . $xform_id, "refresh");
        }
    }
    /**
     * get column name from number
     *
     * @param $number
     * @return string
     */
    function getColumnLetter($number)
    {
        $numeric = $number % 26;
        $suffix = chr(65 + $numeric);
        $prefNum = intval($number / 26);
        if ($prefNum > 0) {
            $prefix = $this->getColumnLetter($prefNum - 1) . $suffix;
        } else {
            $prefix = $suffix;
        }
        return $prefix;
    }
}
