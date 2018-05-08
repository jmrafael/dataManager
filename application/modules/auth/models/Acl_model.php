<?php
/**
 * Created by PhpStorm.
 * User: akyoo
 * Date: 25/10/2017
 * Time: 08:09
 */

class Acl_model extends CI_Model
{

    /**
     * @var string
     */
    private static $table_name_permissions = "acl_permissions";
    /**
     * @var string
     */
    private static $table_name_filters = "acl_filters";
    /**
     * @var string
     */
    private static $table_name_users_permissions = "acl_users_permissions";


    /**
     * @param $permission
     * @return mixed
     */
    public function create_permission($permission)
    {
        return $this->db->insert(self::$table_name_permissions, $permission);
    }

    /**
     * @param $permission
     * @param $id
     * @return mixed
     */
    public function update_permission($permission, $id)
    {
        return $this->db->update(self::$table_name_permissions, $permission, array('id' => $id));
    }

    /**
     * @param $users_permissions
     * @return mixed
     */
    public function assign_users_permissions($users_permissions)
    {
        return $this->db->insert(self::$table_name_users_permissions, $users_permissions);
    }

    /**
     * @param $user_id
     * @param $permission_id
     * @return mixed
     */
    public function delete_user_permission($user_id = null, $permission_id = null)
    {
        if ($user_id != null)
            $this->db->where("user_id", $user_id);

        if ($permission_id != null)
            $this->db->where("permission_id", $permission_id);

        return $this->db->delete(self::$table_name_users_permissions);
    }

    /**
     * @param int $limit
     * @param int $offset
     * @return mixed
     */
    public function find_permissions($limit = 100, $offset = 0)
    {
        $this->db->limit($limit, $offset);
        $this->db->order_by('date_created', 'DESC');
        return $this->db->get(self::$table_name_permissions)->result();
    }

    /**
     * @param $id
     * @return mixed
     */
    function find_permission_by_id($id)
    {
        return $this->db->get_where(self::$table_name_permissions, array('id' => $id))->row();
    }

    /**
     * @param $filter
     * @return mixed
     */
    public function create_filter($filter)
    {
        return $this->db->insert(self::$table_name_filters, $filter);
    }

    /**
     * @param int $limit
     * @param int $offset
     * @param null $permission_id
     * @return mixed
     */
    public function find_filters($limit = 100, $offset = 0, $permission_id = null)
    {
        if ($permission_id != null) {
            $this->db->where("permission_id", $permission_id);
        }

        $this->db->limit($limit, $offset);
        $this->db->order_by('date_added', 'DESC');
        return $this->db->get(self::$table_name_filters)->result();
    }

    /**
     * @param $permission_id
     * @return mixed
     */
    public function count_permission_filters($permission_id)
    {
        $this->db->from(self::$table_name_filters);
        $this->db->where("permission_id", $permission_id);
        return $this->db->count_all_results();
    }

    /**
     * @param $user_id
     * @param $table_name
     * @return null|string
     */
    public function find_user_permissions($user_id, $table_name)
    {
        $this->db->select("where_condition");
        $this->db->from(self::$table_name_permissions . "  p");
        $this->db->join(self::$table_name_users_permissions . " up", "up.permission_id=p.id");
        $this->db->join(self::$table_name_filters . " f", "f.permission_id=p.id");
        $this->db->where("up.user_id", $user_id);
        $this->db->where("f.table_name", $table_name);
        $perms = $this->db->get()->result();

        if ($perms) {
            $count = count($perms);
            $condition = "";
            $i = 0;
            foreach ($perms as $p) {
                $condition .= $p->where_condition;
                if ($count > 1 && $i < ($count - 1)) {
                    $condition .= " OR ";
                }
                $i++;
            }
            return $condition;
        }
        return null;
    }
}