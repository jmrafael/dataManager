<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Welcome extends MX_Controller
{

	private $data;

	/**
	 * Index Page for this controller.
	 *
	 * Maps to the following URL
	 *        http://example.com/index.php/welcome
	 *    - or -
	 *        http://example.com/index.php/welcome/index
	 *    - or -
	 * Since this controller is set as the default controller in
	 * config/routes.php, it's displayed at http://example.com/
	 *
	 * So any other public methods not prefixed with an underscore will
	 * map to /index.php/welcome/<method_name>
	 *
	 * @see https://codeigniter.com/user_guide/general/urls.html
	 */

	public function __construct()
	{
		$this->load->model("Xform_model");
	}

	public function index()
	{
		$this->events_map();
	}

	public function events_map()
	{
		$geo_data = $this->Xform_model->get_geospatial_data("ad_build_AfyaData_Demo_1500530768");

		$geo_data_array = [];
		$count = 0;
		foreach ($geo_data as $data) {
			if (isset($data['_xf_c6a6184e0be6372480cae841cc28dba4'])) {
				$geo_data_array[$count]['event'] = $data['_xf_72485ff63b11061b01c236b9c62b58bd'];
				$points = explode(" ", $data['_xf_c6a6184e0be6372480cae841cc28dba4']);
				$geo_data_array[$count]['lat'] = $points[0];
				$geo_data_array[$count]['lng'] = $points[1];
				$count++;
			}
		}

		$data['geo_data_json'] = json_encode($geo_data_array);
		$data['events_map'] = TRUE;

		$this->load->view('layout/header', $data);
		$this->load->view('health_map_view', $data);
		$this->load->view('layout/footer');
	}

	public function get_events()
	{
		$table_name = "ad_build_AfyaData_Demo_1500530768";


		$config = [
			'base_url'    => $this->config->base_url("welcome/get_events/"),
			'total_rows'  => $this->Xform_model->count_all_records($table_name),
			'uri_segment' => 4,
			'per_page' => 2,
		];

		$this->pagination->initialize($config);
		$page = ($this->uri->segment(3)) ? $this->uri->segment(3) : 0;

		$events = $this->Xform_model->get_geospatial_data($table_name, $this->pagination->per_page, $page);
		$links = $this->pagination->create_links();

		if ($this->input->is_ajax_request()) {
			$result = [
				'status'       => "success",
				"events_count" => $config['total_rows'],
				"events"       => $events,
				"links"        => $links
			];
			echo json_encode($result);
			log_message("debug", "Result " . json_encode($result));
		} else {
			show_error("Contact admin", 501);
		}
	}

	public function about()
	{
		$this->data['title'] = 'Taarifa kwa wakati';
		$this->data['about_page'] = TRUE;
		//render view
		$this->load->view('layout/header', $this->data);
		$this->load->view('view');
		$this->load->view('layout/footer');
	}
}
