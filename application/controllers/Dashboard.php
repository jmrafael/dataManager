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
 * @package	    AfyaData
 * @author	    AfyaData Dev Team
 * @copyright	Copyright (c) 2016. Southen African Center for Infectious disease Surveillance (SACIDS http://sacids.org)
 * @license	    http://opensource.org/licenses/MIT	MIT License
 * @link	    https://afyadata.sacids.org
 * @since	    Version 1.0.0
 */

defined('BASEPATH') OR exit('No direct script access allowed');

class Dashboard extends CI_Controller
{
    function __construct()
    {
        parent::__construct();
        $this->load->model(array('User_model', 'Submission_model', 'Campaign_model', 'Feedback_model'));
    }

    /**
     * Check login
     *
     * @return response
     */
    function _is_logged_in()
    {
        if (!$this->ion_auth->logged_in()) {
            // redirect them to the login page
            redirect('auth/login', 'refresh');
        }
    }


    public function index()
    {
        //check if logged in
        $this->_is_logged_in();

        //statistics
        $this->data['active_users'] = $this->User_model->count_users();
        $this->data['published_forms'] = $this->Submission_model->count_published_forms();
        $this->data['active_campaign'] = $this->Campaign_model->count_active_campaign();
        $this->data['new_feedback'] = $this->Feedback_model->count_new_feedback();

        //submitted forms
        $this->data['submitted_forms'] = $this->Submission_model->get_submitted_forms();

        foreach ($this->data['submitted_forms'] as $k => $form) {
            $this->data['submitted_forms'][$k]->overall_form = $this->Submission_model->count_overall_submitted_forms($form->form_id);
            $this->data['submitted_forms'][$k]->monthly_form = $this->Submission_model->count_monthly_submitted_forms($form->form_id);
            $this->data['submitted_forms'][$k]->weekly_form = $this->Submission_model->count_weekly_submitted_forms($form->form_id);
            $this->data['submitted_forms'][$k]->daily_form = $this->Submission_model->count_daily_submitted_forms($form->form_id);
        }

        $this->data['title'] = "Taarifa kwa wakati!";
        $this->load->view('header', $this->data);
        $this->load->view('index');
        $this->load->view('footer');
    }

}

/* End of file dashboard.php */
/* Location: ./application/controllers/dashboard.php */
