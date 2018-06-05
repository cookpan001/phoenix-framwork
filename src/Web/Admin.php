<?php
namespace Phoenix\Web;

/**
 * Description of Admin
 *
 * @author zhupeng <zhupeng@davdian.com>
 */
class Admin
{
    public static function execute()
    {
        ob_start();
        $app = new self();
        if(!$app->isAjax()){
            include __DIR__ . DIRECTORY_SEPARATOR . 'template' . DIRECTORY_SEPARATOR . 'admin.html';
        }
        $str = ob_get_contents();
        Response::succ($str, true);
        ob_clean();
    }

    public static function outputJs()
    {
        $js = array('basic-admin.js', 'add-admin.js');
        $str = '';
        foreach($js as $file){
            $str .= "<script src='js/{$file}'></script>";
        }
        return $str;
    }
    
    public static function outputMenu()
    {
        $env = defined('ENV') ? ENV : 'none';
        $str = "Hi, ENV: $env <br/><a href='/admin/login.php?action=quit'>Exit</a><br><br>";
        foreach(self::$menus as $k => $v){
            if(empty($v)){
                $str .= '<br/>';
                continue;
            }
            if(is_int($k)){
                $str .= $v.'<br/>';
                continue;
            }
            $str .= "<li><a href='{$v}.php'>$k</a></li>";
        }
        $main = <<<EOS
        <div style="float: left; margin-right: 20px; height: 100%;">
            <ul style="list-style-type: none;">$str</ul>
        </div>
EOS;
        return $main;
    }
    
    public static function getSetting()
    {
        return array(
            'header' => 'Index',
            'dao' => '',
            'bitops' => array(),
            'primary' => array('id'),
            'readonly' => array('id',),
            'hint' => array(),
            'types' => array(),
            'values' => array(),
        );
    }

    public static function getConf($key = '')
    {
        $class = get_called_class();
        static $arr = array();
        if(!isset($arr[$class])){
            $arr[$class] = $class::getSetting();
        }
        if(!empty($key)){
            if(isset($arr[$class][$key])){
                return $arr[$class][$key];
            }
            return array();
        }
        return !empty($arr[$class]) ? $arr[$class] : array();
    }
    
    public static function updateAjax()
    {
        $w = filter_input(INPUT_POST, 'where');
        $where = array();
        $arr = explode('&', $w);
        foreach($arr as $item){
            if(empty($item)){
                continue;
            }
            $line = explode('=', $item);
            if(empty($line) || count($line) < 2){
                continue;
            }
            $where[$line[0]] = $line[1];
        }
        $key = filter_input(INPUT_POST, 'key');
        $val = filter_input(INPUT_POST, 'val');
        $class = static::getConf('dao');
        $ret = $class::updateData(array($key => $val), $where);
        return json_encode($ret);
    }
    
    public static function getFields()
    {
        static $fields = null;
        if(is_null($fields)){
            $class = static::getConf('dao');
            $fields = $class::fields();
        }
        return $fields;
    }
    
    public static function addAjax()
    {
        $fields = static::getFields();
        $arr = array();
        foreach($fields as $field){
            $arr[$field] = self::input($field);
        }
        $class = static::getConf('dao');
        $class::addData($arr);
        return $arr;
    }
    
    public static function addContent()
    {
        $add = self::getConf('add');
        if(!is_array($add) && empty($add)){
            return '';
        }
        $fields = self::getConf('addFields');
        $primary = self::getConf('primary');
        $readonly = self::getConf('readonly');
        $hint = self::getConf('hint');
        if(!$fields){
            if(count($primary) > 1){
                $fields = self::getFields();
            }else{
                $fields = array_diff(self::getFields(), $primary);
            }
        }
        $addStr = "<form id='addForm' action='?action=add' method='post'><input type='submit' value='ADD' /><br/><table>";
        $addStr .= "<tr>";
        foreach($fields as $field){
            $h = isset($hint[$field]) ? $hint[$field] : '';
            $addStr .= "<th title='$h'>$field</th>";
        }
        $addStr .= "</tr><tr>";
        $types = static::getConf('types');
        $values = static::getConf('values');
        $classArr = static::getConf('class');
        $selectRaw = static::getConf('selectRaw');
        $selectKey = static::getConf('selectKey');
        foreach($fields as $field){
            $canEdit = ($readonly && in_array($field, $readonly)) ? 'readonly' : '';
            $class = isset($classArr[$field]) ? $classArr[$field] : '';
            if(isset($types[$field]) && $types[$field] == 'select'){
                $addStr .= "<td><select name='$field' $canEdit>";
                if(empty($values[$field])){
                    $values[$field] = array();
                }
                foreach((array)$values[$field] as $k => $v){
                    if(in_array($field, $selectRaw)){
                        $addStr .= "<option value='$v'>{$v}</option>";
                    }else if(in_array($field, $selectKey)){
                        $addStr .= "<option value='$v'>{$v}</option>";
                    }else{
                        $addStr .= "<option value='$k'>{$k}-{$v}</option>";
                    }
                }
                $addStr .= "</select></td>";
            }else if(isset($types[$field]) && $types[$field] == 'checkbox'){
                $addStr .= "<td nowrap='nowrap'>";
                $i = 0;
                foreach($values[$field] as $k => $v){
                    if(in_array($field, $selectRaw)){
                        $addStr .= "<input type='checkbox' name='{$field}[]' value='$v' $canEdit />{$v}";
                    }else if(in_array($field, $selectKey)){
                        $addStr .= "<input type='checkbox' name='{$field}[]' value='$k' $canEdit />{$v}";
                    }else{
                        $addStr .= "<input type='checkbox' name='{$field}[]' value='$v' $canEdit />{$k}-{$v}";
                    }
                    if($i > 0 && $i % 4 == 0){
                        $addStr .= "<br/>";
                    }
                    ++$i;
                }
                $addStr .= "</td>";
            }else if(isset($types[$field]) && $types[$field] == 'textarea'){
                $addStr .= "<td><textarea name='$field' $canEdit/></textarea></td>";
            }else if(isset($types[$field]) && $types[$field] == 'none'){
                $addStr .= "<td><div name='$field'></div></td>";
            }else{
                $addStr .= "<td><input type='text' class='$class' size=10 name='$field' $canEdit/></td>";
            }
        }
        $addStr .= "</tr></table></form>";
        return $addStr;
    }
    
    public static function downloadAjax()
    {
        $fields = static::getFields();
        header('Content-Description: File Transfer');
        header('Content-Type: text/csv;charset=utf-8');
        header('Content-Disposition: attachment; filename='.static::getConf('header').'_' . date("YmdHis") . '.csv');
        header('Content-Transfer-Encoding: binary');
        header('Expires: 0');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');
        $handle = fopen("php://output", 'w');
        fputcsv($handle, $fields);
        $class = static::getConf('dao');
        $list = $class::getData();
        foreach($list as $item){
            \Phoenix\Util\GdsStream::down($item);
            fputcsv($handle, $item);
        }
        fclose($handle);
    }
    
    public static function toUTF8($str)
    {
        $encoding = mb_detect_encoding($str);
        if($encoding == 'GB18030' ){
            return mb_convert_encoding($str, 'UTF-8', $encoding);
        }
        return $str;
    }
    
    public static function downloadContent()
    {
        $download = self::getConf('download');
        if(!is_array($download) && empty($download)){
            return '';
        }
        $str = "<a target='_blank' href='?action=download&ajax=1'>Download</a>";
        return $str;
    }
    
    public static function uploadAjax()
    {
        $filename = $_FILES['file']['tmp_name'];
        $handle = fopen($filename, 'r');
        if(empty($handle)){
            echo "<pre>";
            var_dump(error_get_last());
            echo "</pre>";
            return false;
        }
        $arr = array();
        $i = 0;
        $fields = self::getFields();
        $nextId = 0;
        while(false !==($line = fgetcsv($handle, 0))){
            ++$i;
            if($i == 1){
                continue;
            }
            if(count($line) != count($fields)){
                echo "<pre>";
                echo "上传文件列数不正确，实际：".count($line)."要求：".count($fields);
                echo "line:".print_r($line,true)."\n";
                echo "</pre>";
                return false;
            }
            \Phoenix\Util\GdsStream::up($line);
            if($fields[0] == 'id' && $nextId < $line[0]){
                $nextId = $line[0];
            }
            $arr[] = $line;
        }
        fclose($handle);
        $class = static::getConf('dao');
        $class::addUploadData($arr, $fields);
        if($nextId){
            $class::setNextId($nextId + 1);
        }
        return $arr;
    }
    
    public static function uploadContent()
    {
        $upload = self::getConf('upload');
        if(!is_array($upload) && empty($upload)){
            return '';
        }
        $str = "<form action='?' method='post' enctype='multipart/form-data'>";
        $str .= "<input type='file' name='file' value=''/><br/><br/>";
        $str .= "<input type='hidden' name='action' value='upload'/>";
        $str .= "<input type='submit' value='Upload' />";
        $str .= "</form>";
        return $str;
    }
    
    public static function selectContent()
    {
        $select = self::getConf('select');
        if(empty($select)){
            $select = self::getConf('selectRaw');
            if(empty($select)){
                return '';
            }
        }
        $types = static::getConf('types');
        $values = static::getConf('values');
        $selectRaw = static::getConf('selectRaw');
        $classArr = static::getConf('class');
        $str = "<form action='?' method='POST'>筛选&nbsp;&nbsp;";
        foreach($select as $field){
            if(!isset($types[$field])){
                continue;
            }
            $rawVal = isset($_REQUEST['select'][$field]) ? $_REQUEST['select'][$field] : '';
            if($types[$field] == 'select'){
                $str .= "$field:<select name='select[$field]'><option value=''>...</option>";
                if(isset($values[$field])){
                    foreach ($values[$field] as $k => $val){
                        if(in_array($field, $selectRaw)){
                            if($rawVal == $val){
                                $str .= "<option value='$val' selected>$val</option>";
                            }else{
                                $str .= "<option value='$val'>$val</option>";
                            }
                        }else{
                            if($rawVal == $k){
                                $str .= "<option value='$k' selected>$val($k)</option>";
                            }else{
                                $str .= "<option value='$k'>$val($k)</option>";
                            }
                        }
                    }
                }
                $str .= "</select>";
            }else{
                $class = isset($classArr[$field]) ? $classArr[$field] : '';
                $str .= "$field:<input type='text' name='select[$field]' class='$class' value='$rawVal'/>";
            }
        }
        $str .= "<input type='submit'/></form>";
        return $str;
    }
    
    public static function getListData()
    {
        $where = array();
        if(isset($_REQUEST['select'])){
            $select = $_REQUEST['select'];
            foreach($select as $k => $v){
                if(trim($v) == ''){
                    continue;
                }
                $where[$k] = trim($v);
            }
        }else if(static::getConf('where')){
            $where = static::getConf('where');
        }
        $class = static::getConf('dao');
        $orderby = static::getConf('orderby');
        if($orderby){
            return $class::getData($where, array('orderby' => $orderby));
        }else{
            return $class::getData($where);
        }
    }
    
    public static function outTip()
    {
        return '';
    }
    
    public static function outputContent()
    {
        static::runAction(false);
        $tip = static::outTip();
        $list = static::getListData();
        $actions = 1|2|3|4|8;
        if(($actions & 1) > 0){
            $addStr = static::addContent();
        }else{
            $addStr = '';
        }
        $selectStr = static::selectContent();
        if(($actions & 4) > 0){
            $uploadStr = static::uploadContent();
        }else{
            $uploadStr = '';
        }
        if(($actions & 8) > 0){
            $downloadStr = static::downloadContent();
        }else{
            $downloadStr = '';
        }
        $ids = static::getConf('primary');
        $str = "{$tip}<br/>{$addStr}<br/>{$selectStr}<table border=1>";
        $keys = self::getFields();
        $str .= "<tr><td colspan='".  count($keys)."'>".$uploadStr."</td></tr>";
        $str .= "<tr><td colspan='".  count($keys)."'>".$downloadStr."</td></tr>";
        $str .= "<tr><th>".implode("</th><th>", $keys)."</th></tr>";
        foreach($list as $item){
            $idStr = '';
            if(isset($item['id'])){
                $idStr = 'id='.$item['id'];
            }else{
                $tmp = array();
                foreach($ids as $id){
                    $tmp[] = "$id={$item[$id]}";
                }
                $idStr = implode('&', $tmp);
            }
            $str .= "<tr class='data_line' key='$idStr'>" . static::td($item) . "</tr>";
        }
        $str .= "</table>";
        return $str;
    }
    
    public static function td($item)
    {
        $str = '';
        $conf = static::getConf('readonly');
        foreach($item as $field => $v){
            $readonly = '1';
            $title = '';//static::getTitle($field, $v);
            if(in_array($field, $conf)){
                $readonly = '0';
            }
            $str .= "<td key='{$field}' title='$title' edit={$readonly}>{$v}</td>";
        }
        return $str;
    }
    
    public static function output($disableMenu = false)
    {
        if($disableMenu) {
            $menu = "";
        }else{
            $menu = static::outputMenu();
        }
        $content = static::outputContent();
        $js = static::outputJs();
        $values = json_encode(static::getConf('values'));
        $types = json_encode(static::getConf('types'));
        $deps = json_encode(static::getConf('deps'));
        $bitops = json_encode(static::getConf('bitops'));
        $main = <<<EOS
<!DOCTYPE HTML>
<html>
    <head>
        <title>
            Chupinxiu Admin Board
        </title>
        <meta charset="UTF-8">
        <link rel='stylesheet' media="all" type="text/css" href='css/jquery-ui.css'>
        <style type="text/css">
        table
          {
          border-collapse:collapse;
          margin:0;
          padding:0;
          }

        table, td, th
          {
          border:1px solid black;
          margin:0;
          padding:0;
          }
        </style>
    </head>
    <body><div>$menu<div style='margin-left: 250px;'>$content</div></div>
    <script src="js/jquery.min.js"></script>
    <script src="js/jquery-ui.min.js"></script>
    <script src="js/jquery-ui-timepicker-addon.min.js"></script>
    <script>
        var values={$values};var types={$types};var deps={$deps};var bitops={$bitops};
    </script>
    $js
    </body>
</html>
EOS;
        return $main;
    }
    
    public static function getData($where){}
    public static function updateData($vals, $where){}
    
    public static function input($key, $isArray = false)
    {
        if($isArray){
            $ret = filter_input(INPUT_POST, $key, FILTER_DEFAULT , FILTER_REQUIRE_ARRAY);
            if(is_null($ret) || false === $ret){
                return filter_input(INPUT_GET, $key, FILTER_DEFAULT , FILTER_REQUIRE_ARRAY);
            }
            return $ret;
        }
        $action = filter_input(INPUT_POST, $key);
        if(is_null($action) || false === $action){
            $action = filter_input(INPUT_GET, $key);
        }
        return $action;
    }
    
    public static function runAction($ajax = true)
    {
        $action = static::input('action');
        if(empty($action)){
            return;
        }
        if($action == 'update'){
            $key = filter_input(INPUT_POST, 'key');
            if(in_array($key, static::getConf('readonly'))){
                if($ajax){
                    echo 'false';
                    exit;
                }
                return 'false';
            }
        }
        $method = $action.'Ajax';
        $ret = '';
        $classname = get_called_class();
        if(method_exists($classname, $method)){
            $ret = $classname::$method();
        }
        if($ajax){
            echo $ret;
            exit;
        }
        return $ret;
    }
    
    public static function run($disableMenu = false)
    {
        //basic admin cookie author
        $requestUrl = $_SERVER['REQUEST_URI'];
        $specUrl = "/admin/login.php";
        $specUrlLen = strlen($specUrl);
        $subUrl = substr($requestUrl, 0, $specUrlLen);
        if($subUrl != $specUrl){
            //self::adminSessionCheck();
        }
        $ajax = static::input('ajax');
        if($ajax == 1){
            static::runAction();
        }else{
            echo static::output($disableMenu);
        }
    }
}