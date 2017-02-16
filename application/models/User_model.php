<?php

/**
 * User_model.php
 * model for users
 * Author : Renfrid Ngolongolo
 */
class User_model extends CI_Model
{

    private static $table_name = "users";
    private static $groups_table_name = "groups";
    private static $users_groups_table_name = "users_groups";

    function __construct()
    {
        parent::__construct();
    }

    public function create($user)
    {
        return $this->db->insert(self::$table_name, $user);
    }

    function count_users()
    {
        return $this->db->get(self::$table_name)->num_rows();
    }

    /**
     * @return mixed
     * @param $limit
     * @param $offset
     */
    function get_users($limit = 100, $offset = 0)
    {
        $users = $this->db
            ->limit($limit, $offset)
            ->get(self::$table_name)->result();
        return $users;
    }

    function get_user($user_id)
    {
        $users = $this->db->get_where('users', array('users.id' => $user_id))->row();
        return $users;
    }

    function get_user_details($username)
    {
        $query = $this->db->get_where('users', array('username' => $username));
        return $query->row();
    }

    function delete_user($user_id)
    {
        $this->db->delete('users', array('users.id' => $user_id));
    }

    /**
     * @param $user_id
     * @return mixed
     */
    function find_by_id($user_id)
    {
        $this->db->where("id", $user_id);
        return $this->db->get(self::$table_name)->row(1);
    }

    /**
     * @param $user_id
     * @return string
     */
    function _user_details($user_id)
    {
        $query = $this->db->get_where(self::$table_name, array('id' => $user_id))->row();
        return $query->first_name . ' ' . $query->last_name;
    }

    /**
     * @param $username
     * @return mixed
     */
    function find_by_username($username)
    {
        $query = $this->db->get_where(self::$table_name, array('username' => $username));
        return $query->row();
    }

    /**
     * Initializes table names from configuration files
     */
    private function initialize_table()
    {
        self::$xform_table_name = $this->config->item("table_users");
    }

    public function find_user_groups()
    {
        return $this->db->get(self::$groups_table_name)->result();
    }

    public function get_user_groups_by_id($user_id)
    {
        $this->db->select("ug.*,g.*");
        $this->db->from(self::$users_groups_table_name . " ug");
        $this->db->join(self::$table_name . " u", "u.id = ug.user_id");
        $this->db->join(self::$groups_table_name . " g", "g.id = ug.group_id");
        $this->db->where("ug.user_id", $user_id);
        return $this->db->get()->result();
    }

    public function search_users($first_name = NULL, $last_name = NULL, $phone = NULL, $status = NULL, $limit = 30, $offset = 0)
    {
        if ($first_name != NULL)
            $this->db->or_like("first_name", $first_name);

        if ($last_name != NULL)
            $this->db->or_like("last_name", $last_name);

        if ($phone != NULL)
            $this->db->or_where("phone", $phone);

        if ($status != NULL)
            $this->db->where("active", $status);
        $this->db->limit($limit, $offset);
        return $this->db->get(self::$table_name)->result();
    }

    public function count_users_by_search_terms($first_name = NULL, $last_name = NULL, $phone = NULL, $status = NULL)
    {
        if ($first_name != NULL)
            $this->db->or_like("first_name", $first_name);

        if ($last_name != NULL)
            $this->db->or_like("last_name", $last_name);

        if ($phone != NULL)
            $this->db->or_where("phone", $phone);

        if ($status != NULL)
            $this->db->where("active", $status);
        return $this->db->get(self::$table_name)->num_rows();
    }
}

/* End of file users_model.php */
/* Location: ./application/model/survey_model.php */