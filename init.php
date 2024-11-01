<?php
/*
Plugin Name: Widget Block
Plugin URI: http://pods.uproot.us/
Description: Create widgets that only display on specified pages.
Version: 1.0.1
Author: Matt Gibbs
Author URI: http://pods.uproot.us/

Copyright 2009  Matt Gibbs  (email : logikal16@gmail.com)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/
class Widget_Block extends WP_Widget
{
    /** constructor */
    function Widget_Block()
    {
        $widget_opts = array('classname' => 'widget-block', 'description' => __('Display widgets on a per-page basis.'));
        $this->WP_Widget('block', __('Block'), $widget_opts);
    }

    /** @see WP_Widget::widget */
    function widget($args, $instance)
    {
        global $table_prefix;

        extract($args);
        $id = $instance['id'];
        $code = $instance['code'];
        $title = $instance['title'];
        $method = $instance['method'];
        $show_label = empty($instance['show_label']) ? 0 : 1;

        // Get the current page's URL
        $home = explode('://', get_bloginfo('url'));
        $uri = explode('?', $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
        $uri = str_replace($home[1], '', $uri[0]);
        $uri = preg_replace("@^([/]?)(.*?)([/]?)$@", "$2", $uri);
        $uri = mysql_real_escape_string($uri);
        if (empty($uri))
        {
            $uri = '<front>';
        }

        $sql = "
        SELECT
            widget_id
        FROM
            {$table_prefix}blocks
        WHERE
            widget_id = '$id' AND '$uri' LIKE REPLACE(url, '*', '%')
        LIMIT
            1
        ";
        $result = mysql_query($sql);
        $total_rows = mysql_num_rows($result);

        if (('exclude' == $method && 0 == $total_rows) || ('include' == $method && 0 < $total_rows))
        {
            echo $before_widget;
            if ($show_label)
            {
                echo $before_title . $title . $after_title;
            }

            ob_start();
            eval("?>$code");
            echo ob_get_clean() . $after_widget;
        }
    }

    /** @see WP_Widget::update */
    function update($new_instance, $old_instance)
    {
        global $table_prefix;

        // Create the table if it doesn't exist
        $result = mysql_query("SHOW TABLES LIKE '{$table_prefix}blocks'");
        if (1 > mysql_num_rows($result))
        {
            mysql_query("CREATE TABLE {$table_prefix}blocks (widget_id INT unsigned, url VARCHAR(128), method CHAR(7), show_label TINYINT(1))");
        }
        else
        {
            $result = mysql_query("SHOW COLUMNS FROM {$table_prefix}blocks LIKE 'show_label'");
            if (1 > mysql_num_rows($result))
            {
                mysql_query("ALTER TABLE {$table_prefix}blocks ADD COLUMN `show_label` TINYINT(1)");
            }
        }

        // Update the wp_blocks table
        $id = $new_instance['id'];
        $pages = explode("\n", trim($new_instance['pages']));
        $method = $new_instance['method'];
        $show_label = empty($new_instance['show_label']) ? 0 : 1;

        mysql_query("DELETE FROM {$table_prefix}blocks WHERE widget_id = '$id'");

        // If the type is "exclude" and the URL list is empty, then add a single item anyways
        if ('exclude' == $method && empty($pages))
        {
            mysql_query("INSERT INTO {$table_prefix}blocks (widget_id, url, method, show_label) VALUES ('$id', '', '$method', '$show_label')");
        }
        elseif (!empty($pages))
        {
            $tupples = array();
            foreach ($pages as $key => $url)
            {
                $tupples[] = "('$id', '$url', '$method', '$show_label')";
            }
            $tupples = implode(',', $tupples);
            mysql_query("INSERT INTO {$table_prefix}blocks (widget_id, url, method, show_label) VALUES $tupples");
        }

        return $new_instance;
    }

    /** @see WP_Widget::form */
    function form($instance)
    {
?>

<style type="text/css">
.wb {
    font-weight: bold;
}

.wb-help {
    font-size: 0.8em;
}

.wb-code {
    height: 160px;
}

.wb-pages {
    height: 80px;
}
</style>

<?php
$method = array('include' => '', 'exclude' => '');
empty($instance['method']) ? $method['include'] = 'checked' : $method[$instance['method']] = 'checked';
$show_label = empty($instance['show_label']) ? '' : 'checked';
?>

<div class="wb">Label</div>
<input type="hidden" name="<?php echo $this->get_field_name('id'); ?>" value="<?php echo $this->number; ?>" />
<div>
    <input type="text" id="<?php echo $this->get_field_id('title'); ?>" class="widefat" name="<?php echo $this->get_field_name('title'); ?>" value="<?php echo $instance['title']; ?>" />
    <input type="checkbox" id="<?php echo $this->get_field_id('show_label'); ?>" name="<?php echo $this->get_field_name('show_label'); ?>" <?php echo $show_label; ?> /> Show Label?
</div>
<div class="wb">Widget Code</div>
<div><textarea class="wb-code widefat" name="<?php echo $this->get_field_name('code'); ?>"><?php echo $instance['code']; ?></textarea></div>
<div>
    <input type="radio" name="<?php echo $this->get_field_name('method'); ?>" value="include" <?php echo $method['include']; ?> /> Include pages below<br />
    <input type="radio" name="<?php echo $this->get_field_name('method'); ?>" value="exclude" <?php echo $method['exclude']; ?> /> Exclude pages below
</div>
<div class="wb">Page URLs</div>
<div><textarea class="wb-pages widefat" name="<?php echo $this->get_field_name('pages'); ?>"><?php echo $instance['pages']; ?></textarea></div>
<div class="wb-help">One URL per line. Do not use beginning or trailing slashes. <code>&lt;front></code> for the frontpage. Wildcard URLs (e.g. <code>about/staff/*</code>) and PHP support available.</div>
<?php
    }
}

add_action('widgets_init', create_function('', 'return register_widget("Widget_Block");'));
