<?php
// Function Explanation
    // This function is used for providers that do not send a true array for message media.
    // It will take a $searchArray, which is typically a $_GET or $_POST array,
    // and get all of the array keys that start with one of the entries in the $keys array
    // and append them to a new array.
    // Keys added to the new array will be suffixed with $index

    // This is used for inbound message media array

    // Example usage: 
    //      $keys = ['message_media_url', 'message_media_type'];
    //      $arr = message_media_builder($_POST, $keys);

    // Contents of $arr
    //      [
    //          'message_media_url0' => 'https://sms.provider.example.com/img1.jpg',
    //          'message_media_type0' => 'image/jpeg',
    //          'message_media_url1' => 'https://sms.provider.example.com/img1.jpg',
    //          'message_media_type1' => 'image/jpeg'
    //      ]

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