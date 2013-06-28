<?php

/* 
 * Use Geocoding to turn a Gedcom file into GEDJSON
 * 
 * The resulting GeoJSON will have every ancestor as a feature.
 */


spl_autoload_register(function ($class) {
    $pathToPhpGedcom = __DIR__ . '/php-gedcom/library/'; 

    if (!substr(ltrim($class, '\\'), 0, 7) == 'PhpGedcom\\') {
        return;
    }

    $class = str_replace('\\', DIRECTORY_SEPARATOR, $class) . '.php';
    if (file_exists($pathToPhpGedcom . $class)) {
        require_once($pathToPhpGedcom . $class);
    }
});

class ged2json {
    var $gedcom;                    // The PhpGedcom parsed object
    var $geocodeResults = FALSE;    // Should places be geocoded
    var $ancestors      = Array();  // Places to stash ancestors we come across

    // These are the events we want to keep if we come across them
    // They need to be in order of desirability for placing on the map
    var $summaryEvents = Array('BIRT','DEAT','MARR','DIV');

    /**
     * @brief Mkae a gedToJson object
     *
     * @param $gedcomFile (required) The file to parse
     */
    public function __construct($gedcomFile){
        // Get the gedcome parameter and ensure that it exists
        if(!file_exists($gedcomFile)){
            throw new Exception("Gedcom file $gedcomFile does not exist");
        }

        // Parse the given file
        $parser = new PhpGedcom\Parser();
        $this->gedcom = $parser->parse($gedcomFile);
    }

    /**
     * @brief Return the the gedjson as an array
     *
     * @param $summary (bool/TRUE) Show Summary?
     */
    public function toJsonArray($summary = FALSE){
        $ancestors = $this->parse($summary);

        if($summary){
           $this->filter($ancestors); 
        }

        return $ancestors;
    }

    /**
     * @brief Return a hash with the GEDCOM IDs as the keys
     *
     * @param $summary (bool/TRUE) Show Summary?
     */
    public function toJsonHash($summary = FALSE){
        $ancestors = $this->toJsonArray($summary);
        $ahash = Array();
        foreach($ancestors as $ancestor){
            $ahash[$ancestor['id']] = $ancestor;
        }
        return $ahash;
    }


    /**
     * @brief Return the gedjson as a string
     */
    public function toJson($summary = TRUE){
        return json_encode($this->toJsonArray($summary));
    }

    /**
     * @brief Magic method to stringify as needed
     */
    public function __toString(){
        return $this->toJson();
    }


    /**
     * @brief Parse the entire GEDCOM file
     *
     * @return Returns an array of ancestors
     */
    protected function parse($summary = TRUE){
        // Sort by refdate
        $ancestors = Array();

        // Make an array of ancestors
        foreach($this->gedcom->getIndi() as $individual){

            // Make a person object to save off later
            $person = Array();

            // Set the personID
            $person['id'] = $individual->getId(); 

            // Set name data
            if($names = $individual->getName()){
                $name = $names[0];
                $person['name'] = $name->getName();
                if(!$summary){
                    foreach($names as $name){
                        $person['names'][] = $name->getName();
                    }
                }
            }

            // Set gender (M|F) or default to U
            $sex = $individual->getSex();
            if(is_null($sex)){
                $sex = 'U';
            }
            $person['gender'] = $sex;

            //  Loop through events
            foreach($individual->getEven() as $event){
                $person['events'][] = $this->parseEvent($event);
            }

            // Save the person
            $ancestors[$person['id']] = $person;
        }

        // Get events and relationships from the family objects
        foreach($this->gedcom->getFam() as $family){

            // If we have a spouse and our spouse doesn't have a reference date, we use our reference date
            // Not genealogically certain, but good enough for our purposes
            $wifeId = $family->getWife();
            $husbId = $family->getHusb();
            if($wifeId && $husbId){
                $ancestors[$husbId]['wife'][] = $wifeId;
                $ancestors[$wifeId]['husb'][] = $husbId;
            }

            foreach($family->getChil() as $childId){
                if($wifeId){
                    $ancestors[$wifeId]['children'][$childId] = $husbId;
                }
                if($husbId){
                    $ancestors[$husbId]['children'][$childId] = $wifeId;
                }
                if($wifeId){
                    $ancestors[$childId]['mothers'][] = $wifeId;
                }
                if($husbId){
                    $ancestors[$childId]['fathers'][] = $husbId;
                }
            }

            foreach($family->getEven() as $event){
                $parsedEvent = $this->parseEvent($event);
                if($wifeId){
                    $ancestors[$wifeId]['events'][] = $parsedEvent;
                }
                if($husbId){
                    $ancestors[$husbId]['events'][] = $parsedEvent;
                }
            }
        }

        $this->sortAncestors($ancestors);

        return $ancestors;
    }

    /**
     * @brief Create a single event object
     *
     * @param $event (required) A single event object
     *
     * @return An events array object
     */
    protected function parseEvent($event){
        $parsedEvent = Array();

        // Set type
        $parsedEvent['type'] = $event->getType();

        // Set Date Maybe
        if($d = $event->getDate()){
            // ear: Event Array
            $parsedEvent['date']['raw'] = $d;
        }

        // Set Place Maybe
        if($eplace = $event->getPlac()){
            if($place = $eplace->getPlac()){
                $parsedEvent['place']['raw'] = $place;
            }
        }

        return $parsedEvent;
    }

    /**
     * @brief Sort ancestors and their events by date
     *
     * @param $ancestors (required) The ancestors array to sort
     */
    protected function sortAncestors(&$ancestors){
        // Two pass sort. First, sort each ancestor's events by date and set the refdate
        // Then sort the ancestors by refdate

        foreach($ancestors as $id => $ancestor){
            if(!array_key_exists('events',$ancestor)){
                continue;
            }

            // Get dates for each event
            foreach($ancestor['events'] as $eventId => $event){
                if(array_key_exists('date',$event)){
                    $parsedate = $this->parseDateString($event['date']['raw']);
                    if($parsedate !== FALSE){
                        $ancestors[$id]['events'][$eventId]['date']['y'] = date('Y',$parsedate); 
                        $month = date('m',$parsedate) - 1;
                        $month = ($month < 0 ? 12 : $month);
                        $ancestors[$id]['events'][$eventId]['date']['m'] = $month; 
                        $ancestors[$id]['events'][$eventId]['date']['d'] = date('d',$parsedate); 
                    }
                }
            }
            
            // Sort events by date
            usort($ancestors[$id]['events'],Array('ged2json','eventUsort'));

            if($refDate = $this->getRefDate($ancestors[$id]['events'])){
                $ancestors[$id]['refdate'] = $refDate;
            }
            if($refPlace = $this->getRefPlace($ancestors[$id]['events'])){
                $ancestors[$id]['refplace'] = $refPlace;
            }

        }

        usort($ancestors,Array('ged2json','ancestorUSort'));
    }

    /**
     * @brief callback function for the refdate usort object
     *
     * @param $a An ancestor
     * @param $b An ancestor
     *
     * @return 0,1,-1
     */
    protected function ancestorUSort($a,$b){
            if(!array_key_exists('refdate',$a) && !array_key_exists('refdate',$b)){
                return 0;
            }
            if(!array_key_exists('refdate',$a)){
                return -1;
            }
            if(!array_key_exists('refdate',$b)){
                return 1;
            }

            $refa = $a['refdate'];
            $refb = $b['refdate'];

            return $this->eventUsort($refa,$refb);
    }

    /**
     * @brief callback function for sorting an array of events
     *
     * @return 0,1,-1
     */
    protected function eventUsort($refa,$refb){
            foreach(Array('y','m','d') as $sortKey){
                if(!array_key_exists($sortKey,$refa) && !array_key_exists($sortKey,$refb)){
                    return 0;
                }

                if(!array_key_exists($sortKey,$refa)){
                    return -1;
                }

                if(!array_key_exists($sortKey,$refb)){
                    return 1;
                }

                $diff = $refa[$sortKey] - $refb[$sortKey];

                if($diff !== 0){
                    return $diff;
                }
            }

            return 0;
    }

    /**
     * @brief Find the date to use as the refdate
     *
     * @note In its own function so it can be overridden easily, for example if 
     * you wanted them sorted only by birth dates
     * 
     * @return The earliest date object for a person
     */
    protected function getRefDate($events){
        // Get the first date as their ref date
        foreach($events as $eventId => $event){
            if(array_key_exists('date',$event)){
                return $event['date'];
            }
        }
    }

    /**
     * @brief Find the place to use as the refplace
     *
     * @note In its own function so it can be overridden easily, for example if 
     * you wanted them sorted only by birth place
     *
     * @return The earliest place object for a person
     */
     protected function getRefPlace($events){
        // Get the first date as their ref date
        foreach($events as $eventId => $event){
            if(array_key_exists('place',$event)){
                return $event['place'];
            }
        }
    } 

    // Print it out
    /**
     * @brief Attempt to parse a datetime string
     *
     * @param $string -- A string from a GEDCOM even date field. Hopefully in a date-like format
     *
     * @return FALSE on failure to parse, or a UNIX timestamp.
     *
     * @note 32-bit PHP is limited to dates between 1970 and 2038. Use PHP 64-bit for larger date ranges
     */
    protected function parseDateString($string){
        // might be a year!
        $ts = date_create_from_format('Y',$string);
        if($ts === FALSE){
            $ts = strtotime($string);
        }else{
            $ts = $ts->getTimestamp();
        }
        if((int)$ts == 0){ return FALSE; }
            return $ts;
    }

    /**
     * @brief Filter out event types that aren't to our liking
     *
     * @param $ancestors (required) The array of ancestors which need their events filtered
     */
    protected function filter(&$ancestors){
        foreach($ancestors as $ancestorId => $ancestor){
                if(!array_key_exists('events',$ancestor)){
                    continue;
                }
                foreach($ancestor['events'] as $eventId => $event){
                    if(!in_array($event['type'],$this->summaryEvents)){
                        unset($ancestors[$ancestorId]['events'][$eventId]);
                    }
                }
                if(count($ancestors[$ancestorId]['events']) === 0){
                    unset($ancestors[$ancestorId]['events']);
                }else{
                    // Reset array, otherwise arrays with missing keys (eg. 0,1,3) will end up being hashes instead of arrays in json
                    $ancestors[$ancestorId]['events'] = array_values($ancestors[$ancestorId]['events']);
                }
            }
    }
}
