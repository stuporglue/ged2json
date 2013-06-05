ged2json
========

ged2json converts [GEDCOM](http://homepages.rootsweb.ancestry.com/~pmcbride/gedcom/55gctoc.htm) 
files into a simple [JSON](http://www.json.org/) format for online display use. 

It comes in two flavors, array and GeoJSON.

It is a one-way format, it is not meant for conversion back to GEDCOM. 

This spec is in progress.

## Genealogical Data Flow

It is assumed that the genealogical data resides in a real genealogy program, 
probably on the user's computer, or possibly online. They then export the data
to the GEDCOM format to publish it on the web. 

ged2json parses that GEDCOM file and produces the JSON file for consumption by
javascript or other applications.

To update the genealogical data on the web the user would update the data in their
desktop program and export a new GEDCOM file. 


## JSON Output

ged2json can output two formats of JSON, either an array of ancestor objects 
or as a [GeoJSON](http://www.geojson.org/) FeatureCollection of points.

All values are optional and can be omitted if not present, except the geometry
in the GeoJSON feature, which must be set to null per the spec.

### Array of Ancestor Objects

If the array of Ancestor Objects output is chosen, the output is a simple array of objects.

    [An array of Ancestor Objects]

### GeoJSON FeatureCollection

The GeoJSON output format is a standard FeatureCollection object which can be 
consumed by any modern web map software. 

    { 
        "type": "FeatureCollection",
        "features": [Array of GeoJSON Features]
    }

### GeoJSON Feature

Each ancestor is represented as a point feature, with their earliest known event 
(hopefully their birth) as the coordinates.

The GeoJSON feature properties is the Ancestor Object. Note that the Ancestor 
Object can contain other coordinates for the ancestor which you could use to 
switch their position on a map over time.

    { 
        "type": "Feature",
        "geometry": {
            "type": "Point", 
            "coordinates": [longitude, latitude]
        },
        "properties": {Ancestor Object}
    }


### Ancestor Object 

A single ancetor is represented as follows:

    {
        id          : GEDCOM ID (eg. I0294),
        name        : Primary Name,
        names       : [An array of all names],
        gender      : F|M|U,
        refdate     : A reference date object for the earliest known date for the ancestor,
        refplace    : A reference set of coordinates for the earliest known geocoded place for the ancestor
        husb  	    : [An array of person IDs],
        wife        : [An array of person IDs],
        mothers     : [An array of wive IDs who claim this individual as a child],
        fathers     : [An array of husband IDs who claim this individual as a child],
        children    : {A child object},
        events      : [An array of event objects]
    }

### Child Object

A hash of all of a person's children, pointing at their other parent.

Unknown parents are left as null (so the child Id key points to a null value)

eg:

    {
        childId1 : spouseId1, 
        childId2 : spouseId1, 
        childId3 : spouseId2, 
        childId4 : null
    }

### Event Objects

Events have a type, a date and a place. If geocoding is selected the place
string is attempted to be geocoded.

    {
        type        : The GEDCOM event type string,
        date        : {Event date object},
        place       : {Place object},
    }

### Date Objects

Date objects are used for the individual and for events

*Note*: Construction JavaScript Date objects means using 0-based month numbers. We follow that wacky convention.

    { 
        raw         : The raw date string,
        y           : The parsed date's year only,
        m           : The parsed date's month only,
        d           : The parsed date's day only
    }

### Place Object

The place object represents a place with a string or coordinates

*Note*: Coordinates are in GeoJSON coordinate order (long,lat)

    {
        raw     : The raw place string from the gedcom,
        geo     : {GeoJSON Feature}
    }

## Sample Implementation


### Examples

Coming real soon.
