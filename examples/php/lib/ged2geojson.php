<?php

/* 
 * Use Geocoding to turn a Gedcom file into GeoJSON
 * 
 * The resulting GeoJSON will have every a GEDJSON ancestor as a feature.
 */

class ged2geojson extends ged2json{

    public function toJsonArray($summary = TRUE){
        $ancestors = $this->parse($summary);

        $this->geocode();

        if(!$details){
           $this->filter($ancestors); 
        }

        $geojson = Array(
            'type' => 'FeatureCollection',
        );

        foreach($ancestors as $ancestorId => $ancestor){

            if(array_key_exists('events',$ancestor)){
                if($refPlace = $this->getRefPlace($ancestor['events'])){
                    $ancestors[$ancestorId]['refplace'] = $refPlace;
                }
            }

            $feature = Array(
                'id' => $ancestor['id'],
                'properties' => $ancestor
            );

            if(array_key_exists('refplace',$ancestor) && array_key_exists('geo',$ancestor['refplace'])){
                $feature['geometry'] = $ancestor['refplace']['geo'];
            }else{
                $feature['geometry'] = NULL;
            }

            $geojson['features'][] = $feature;
        }

        $this->geojson = $geojson;

        return $geojson;
    }

    /**
     * @brief Add geocode info to all events we can
     *
     * @param $ancestors (required) The ancestors array to geocode events for
     *
     * @return void -- modifies events inside of $ancestors
     */
    private function geocode(&$ancestors){
        $places = Array();

        foreach($ancestors as $ancestor){
            if(array_key_exists('events',$ancestor)){
                foreach($ancestor['events'] as $event){
                    if(array_key_exists('place',$event)){
                        $places[] = $event['place']['raw'];
                    }
                }
            }
        }

        $places = array_unique($places);
        require('ssgeocoder/ssgeocoder.php');
        $geocoder = new ssgeocoder('/tmp/test.sqlite');
        $geoplaces = $geocoder->geocode($places);

        foreach($this->ancestors as $ancestorId => $ancestor){
            if(array_key_exists('events',$ancestor)){
                foreach($ancestor['events'] as $eventId => $event){
                    if(array_key_exists('place',$event)){
                        if(array_key_exists($event['place'],$geoplaces)){
                            $this->ancestor[$ancestorId]['events'][$eventId]['geo'] = $geoplaces[$event['place']];
                        } 
                    }
                }
            }
        }
    }

    /**
     * Override the getRefPlace to only accept places with geocoordinates
     */
    private function getRefPlace($events){
        foreach($events as $eventId => $event){
            if(array_key_exists('place',$event) && array_key_exists('geo',$event['place'])){
                return $event['place'];
            }
        }
    }
}
