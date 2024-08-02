<?php	
//Build a media array with keys defined by the elements of the $items array.
//Each key has an auto-incremented number appended to it, starting from 0 ($index)
//Only insert into the media array if all elements with the same index exist in $searchArray
function message_media_builder($searchArray, $keys){
    $mediaArray = [];
    $index = 0;
    while($index >= 0){
        $insert = true;
        foreach ($keys as $key){
            $param = $key.strval($index);
            if(!isset($searchArray[$param])){
                $insert = false;
                break;
            }
            $tmp[$key] = $searchArray[$param];
        }

        if($insert == false){ break; }

        $mediaArray[] = $tmp;
        $index++;
    }
    return $mediaArray;	
}
?>