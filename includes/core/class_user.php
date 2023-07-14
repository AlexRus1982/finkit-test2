<?php

class User {

    // GENERAL

    public static function user_info($user_id) {

        $q = DB::query("SELECT * FROM users WHERE user_id='" . $user_id . "' LIMIT 1;") or die (DB::error());
        if ($row = DB::fetch_row($q)) {
            return [
                'user_id' => (int) $row['user_id'],
                'access' => (int) $row['access'],
                
                'plots'      => User::plots_list_user($row['user_id']),
                'first_name' => $row['first_name'],
                'last_name'  => $row['last_name'],
                'phone'      => phone_formatting($row['phone']),
                'email'      => $row['email'],
                'last_login' => $row['last_login'],
            ];
        } else {
            return [
                'user_id' => 0,
                'access' => 0,

                'plots'      => '',
                'first_name' => '',
                'last_name'  => '',
                'phone'      => '',
                'email'      => '',
            ];
        }
    }

    public static function users_list($d = []) {
        
        // vars
        $search = isset($d['search']) && trim($d['search']) ? $d['search'] : '';
        $offset = isset($d['offset']) && is_numeric($d['offset']) ? $d['offset'] : 0;
        logger($offset);
        $limit = 20;
        $items = [];
        
        // where
        $where = [];
        if ($search) {
            $where[] = "phone LIKE '%" . $search . "%'";
            $where[] = "first_name LIKE '%" . $search . "%'";
            $where[] = "last_name LIKE '%" . $search . "%'";
            $where[] = "email LIKE '%" . $search . "%'";
        }
        $where = $where ? "WHERE ". implode(" OR ", $where) : "";
        
        // info
        $queryStr = "SELECT user_id,
                            first_name,
                            last_name,
                            phone,
                            email,
                            last_login
                     FROM users " . $where . " LIMIT " . $offset . ", " . $limit . ";" ;
        // echo $queryStr;
        $q = DB::query($queryStr) or die (DB::error());
        
        while ($row = DB::fetch_row($q)) {
            $items[] = [
                'user_id'    => (int) $row['user_id'],
                'plots'      => User::plots_list_user($row['user_id']),
                'first_name' => $row['first_name'],
                'last_name'  => $row['last_name'],
                'phone'      => phone_formatting($row['phone']),
                'email'      => $row['email'],
                'last_login' => $row['last_login'],
            ];
        }
        
        // paginator
        $q = DB::query("SELECT count(*) FROM users ".$where.";");
        $count = ($row = DB::fetch_row($q)) ? $row['count(*)'] : 0;
        $url = 'users?';
        if ($search) $url .= '&search='.$search;
        paginator($count, $offset, $limit, $url, $paginator);

        // output
        return ['items' => $items, 'paginator' => $paginator];
    }

    public static function users_fetch($d = []) {
        $info = User::users_list($d);
        HTML::assign('users', $info['items']);
        return ['html' => HTML::fetch('./partials/users_table.html'), 'paginator' => $info['paginator']];
    }    

    public static function users_list_plots($number) {
        // vars
        $items = [];
        
        // info
        $q = DB::query(
            "SELECT user_id, plot_id, first_name, email, phone
            FROM `users` INNER JOIN plots_users USING(user_id)
            WHERE plot_id LIKE '%".$number."%' ORDER BY user_id;"
        ) or die (DB::error());

        while ($row = DB::fetch_row($q)) {
            $plot_ids = explode(',', $row['plot_id']);
            $val = false;
            foreach($plot_ids as $plot_id) if ($plot_id == $number) $val = true;
            if ($val) $items[] = [
                'id' => (int) $row['user_id'],
                'first_name' => $row['first_name'],
                'email' => $row['email'],
                'phone_str' => phone_formatting($row['phone']),
            ];
        }
        // output
        return $items;
    }

    public static function plots_list_user($user_id) {
        // vars
        $items = [];
        
        // info
        $q = DB::query(
            "SELECT user_id, plot_id
            FROM `users` INNER JOIN plots_users USING(user_id)
            WHERE user_id='${user_id}' ORDER BY plot_id;"
        ) or die (DB::error());

        while ($row = DB::fetch_row($q)) {
            $items[] = $row['plot_id'];
        }
        $plot_ids = implode(', ', $items);

        // output
        return $plot_ids;
    }

    // ACTIONS
    public static function user_delete($d = []) {
        $user_id = isset($d['user_id']) && is_numeric($d['user_id']) ? $d['user_id'] : 0;
        $offset = isset($d['offset']) ? preg_replace('~\D+~', '', $d['offset']) : 0;
        
        // delete
        if ($user_id) {
            $queryString = "DELETE FROM users WHERE user_id='${user_id}'";
            DB::query($queryString) or die (DB::error());

            $queryString = "DELETE FROM plots_users WHERE user_id='${user_id}'";
            DB::query($queryString) or die (DB::error());
        }

        // output
        return User::users_fetch(['offset' => $offset]);
    }

    public static function user_edit_window($d = []) {
        $user_id = isset($d['user_id']) && is_numeric($d['user_id']) ? $d['user_id'] : 0;
        HTML::assign('user', User::user_info($user_id));
        return ['html' => HTML::fetch('./partials/user_edit.html')];
    }

    public static function user_edit_update($d = []) {
        // vars
        $user_id = isset($d['user_id']) && is_numeric($d['user_id']) ? $d['user_id'] : 0;
        
        $first_name = isset($d['first_name']) && trim($d['first_name']) ? trim($d['first_name']) : '';
        $last_name = isset($d['last_name']) && trim($d['last_name']) ? trim($d['last_name']) : '';
        $phone = isset($d['phone']) && trim($d['phone']) ? trim($d['phone']) : '';
        $email = isset($d['email']) && trim($d['email']) ? trim($d['email']) : '';
        $plots = isset($d['plots']) && trim($d['plots']) ? trim($d['plots']) : '';
        $plots = $plots ? explode(',', $plots) : [];
        $offset = isset($d['offset']) ? preg_replace('~\D+~', '', $d['offset']) : 0;
        logger($offset);
        
        // update
        $updated = Session::$ts;
        $sqlRequest = "INSERT INTO users (
                            user_id, 
                            first_name, 
                            last_name, 
                            phone, 
                            email, 
                            updated
                        ) VALUES (
                            '{$user_id}',
                            '{$first_name}',
                            '{$last_name}',
                            '{$phone}',
                            '{$email}',
                            '{$updated}'
                        ) 
                        ON DUPLICATE KEY 
                        UPDATE 
                            first_name  = '{$first_name}',
                            last_name   = '{$last_name}',
                            phone       = '{$phone}',
                            email       = '{$email}',
                            updated     = '{$updated}';
                       ";
        DB::query($sqlRequest) or die (DB::error());

        if ($user_id) {
            DB::query("DELETE FROM plots_users WHERE user_id='${user_id}'") or die (DB::error());
        }
        else {
            $user_id = DB::fetch_row(DB::query("SELECT LAST_INSERT_ID();"))['LAST_INSERT_ID()'];
        }

        foreach($plots as $plot_id) {
            $plot_id_trimmed = trim($plot_id);
            DB::query("INSERT INTO plots_users (plot_id, user_id) VALUES ('{$plot_id_trimmed}', '{$user_id}');");
        }

        /*
        if ($user_id) {
            $set = [];
            $set[] = "first_name='".$first_name."'";
            $set[] = "last_name='".$last_name."'";
            $set[] = "phone='".$phone."'";
            $set[] = "email='".$email."'";

            $set[] = "updated='".Session::$ts."'";
            $set = implode(", ", $set);
            DB::query("UPDATE users SET ".$set." WHERE user_id='".$user_id."' LIMIT 1;") or die (DB::error());
        } else {
            DB::query("INSERT INTO users (
                first_name,
                last_name,
                phone,
                email,
                updated
            ) VALUES (
                '".$first_name."',
                '".$last_name."',
                '".$phone."',
                '".$email."',
                '".Session::$ts."'
            );") or die (DB::error());
        }
        */

        // output
        return User::users_fetch(['offset' => $offset]);
    }
}
