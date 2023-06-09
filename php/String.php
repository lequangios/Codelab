<?php
function contain_in_string($str, $words, $case_sensitive = false){
    if(is_array($words)){
        foreach($words as $item) {
            if($case_sensitive){
                if(strpos($str, $item) !== false) {
                    return true;
                }
            }
            else {
                if(strpos(strtolower($str), strtolower($item)) !== false) {
                    return true;
                }
            }
        }
        return false;
    }
    else {
        return strpos(strtoupper($str), strtoupper($words));
    }
}

function readable_size($size){
    if($size < 1000) {
        return "{$size} Bytes";
    }
    else if($size >= 1000 && $size < 1000000) {
        return round($size/1000, 2)." KB";
    }
    else if ($size >= 1000000 && $size < 1000000000) {
        return round($size/1000000, 2)." MB";
    }
    else {
        return round($size/1000000000, 2)." GB";
    }
}