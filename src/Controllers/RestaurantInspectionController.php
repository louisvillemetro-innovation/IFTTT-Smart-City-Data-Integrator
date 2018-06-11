<?php

namespace Src\Controllers;

use KzykHys\CsvParser\CsvParser;
use Slim\Views\Twig as View;
use Illuminate\Database\Capsule\Manager as Capsule;

class RestaurantInspectionController extends Controller
{

    /**
     * This function imports the restaurant inspections data from data.louisvilleky.gov's yelp dataset.
     */
    public function dataImport($request, $response, $args)
    {
        try {
            /**
             * Increase the memory because apparently 128M is not enough.
             */
            ini_set('memory_limit','256M');

            /**
             * Log that we're starting the import task and create the URL variables that contain the CSV site URLs.
             */
            $this->container->logger->info('Starting restaurant inspections import task');
            $businessesFileURL = "http://lky-open-data.s3-website-us-east-1.amazonaws.com/businesses.csv";
            $violationsFileURL = "http://lky-open-data.s3-website-us-east-1.amazonaws.com/violations.csv";
            $inspectionsFileURL = "http://lky-open-data.s3-website-us-east-1.amazonaws.com/inspections.csv";

            /**
             * Pull the businesses list CSV into a string via file_get_contents
             */
            $businessesCSV = file_get_contents($businessesFileURL);

            /**
             * Create the CSV parser using the CSV string that we generated earlier, set the offset to 1 since our
             * first line in the CSV is our column headers.
             */
            $parser = CsvParser::fromString($businessesCSV,['offset'=>1]);

            /**
             * Create an empty restaurants variable. This variable will be used inside the foreach loop to generate
             * our restaurants array.
             */
            $restaurants = null;

            /**
             * Iterate through the CSV parser and start seeding the $restaurants variable that we created earlier with
             * each restaurants information. Please note that we use the restaurants business ID as the key/index for
             * each record, this way we can later match the inspections and violations back to the correct business
             * via this index.
             */
            foreach ($parser as $record) {
                $restaurants[$record[0]] = array(
                    "business_id" => $record[0],
                    "name" => $record[1],
                    "address" => $record[2],
                    "city" => $record[3],
                    "state" => $record[4],
                    "zip" => $record[5],
                    "latitude" => $record[6],
                    "longitude" => $record[7],
                    "phone" => $record[8]
                );
            }

            /**
             * Garbage Collection!
             * Unset parser and business CSV string. We do this to help prevent getting an out of memory error.
             */
            unset($parser);
            unset($businessesCSV);

            /**
             * Same as above, we load up the inspections CSV data and create create our parser from the CSV data
             * string. We also specify an offset of 1 since our first row contains our column headers.
             */
            $inspectionsCSV = file_get_contents($inspectionsFileURL);
            $inspections = CsvParser::fromString($inspectionsCSV,['offset'=>1]);

            /**
             * Iterate through the inspection records. We create a record variable of each inspection which we then
             * insert into the restaurants array by using the business_id column as the index in which to insert
             * then inspection record to. So if an inspection record has a business_id of 173842, it will put
             * it into the restaurant record with that business id as the key. example $restaurants['173842'].
             */
            foreach($inspections as $inspection){
                /**
                 * Create inspection record array containing a specific inspection record.
                 */


                $inspectionRecord = array(
                    "business_id" => $inspection[0],
                    "score" => $inspection[1],
                    "date" => date('m-d-Y',strtotime($inspection[2])),
                    "type" => $inspection[4]
                );

                /**
                 * Insert inspection into correct restaurant record via the business_id index. Also, we set the index
                 * of the inspection, as the date of the inspection, so that we can later tie the inspection violations
                 * to the the correct inspection via the date index.
                 */
                $restaurants[$inspectionRecord['business_id']]['inspections'][$inspectionRecord['date']] = $inspectionRecord;
            }

            /**
             * Garbage Collection!
             * Unset CSV parser and business CSV string. We do this to help prevent getting an out of memory error.
             */
            unset($inspectionsCSV);
            unset($inspections);

            /**
             * Same as above, we load up the violations CSV data and create create our parser from the CSV data
             * string. We also specify an offset of 1 since our first row contains our column headers.
             */
            $violationsCSV = file_get_contents($violationsFileURL);
            $violations = CsvParser::fromString($violationsCSV,['offset'=>1]);

            /**
             * Iterate through the violation records. We create a record variable of each violation which we then
             * insert into the proper inspections record inside the restaurant record. We do this by using the
             * business_id column to identify which restaurant to insert it to, and insert into proper inspection
             * by using the inspection key, which is the date of inspection, which matches the date in the
             * violation record.
             */
            foreach($violations as $violation){
                $violationRecord = array(
                    "business_id" => $violation[0],
                    "date" => date('m-d-Y',strtotime($violation[1])),
                    "description" => $violation[3]
                );

                /**
                 * Insert violations into correct restaurant record via the business_id index.
                 */
                $restaurants[$violationRecord['business_id']]['inspections'][$violationRecord['date']]['violations'][] = $violationRecord;
            }

            /**
             * Garbage Collection!
             * Unset CSV parser and business CSV string. We do this to help prevent getting an out of memory error.
             */
            unset($violationsCSV);
            unset($violations);

            /**
             * We delete all the records in the restaurant inspections table.
             */
            $this->container->db->table('restaurant_inspections')->delete();

            /**
             * Iterate through restaurants array.
             */
            foreach ($restaurants as $index => $restaurant){
                /**
                 * JSON encode all the inspection records before inserting into database.
                 */
                $restaurants[$index]['inspections'] = json_encode($restaurant['inspections']);

                /**
                 * Once the inspections data has been encoded into JSON, we insert the record into the database.
                 */
                $this->container->db->table('restaurant_inspections')->insert($restaurants[$index]);
            }

            /**
             * Log that we've finished the restaurant inspection import task
             */
            $this->container->logger->info('Finished restaurant inpections import task');

            return $response->withStatus(200);
        } catch (\Exception $e) {
            /**
             * If anything went wrong and exceptions were thrown, catch them and log the issue. Return a 500 error back
             * to the user/cron.
             */
            $this->container->logger->error('Could not import restaurant inspections. Error: ' . $e->getMessage());
            return $response->withStatus(500);
        }
    }

    /**
     * When IFTTT queries our server and passes it along an address to get inspections for, this function queries the
     * database for that particular restaurant by address and passes back all inspections to IFTTT.
     */
    function favorite_restaurant_inspections($request, $response, $args){

        /**
         * Parse JSON data that was sent by IFTTT.
         */
        $request_data = json_decode($request->getBody()->getContents(), true);

        /**
         * Check if the triggerFields value is empty. If so, this means that IFTTT did not send us the trigger fields
         * information, therefore, we send back a 400 response and a message saying that the trigger fields are not
         * set.
         */
        if(empty($request_data['triggerFields'])){
            return $response->withHeader('charset','utf-8')->withJson(array('errors' => [
                array('message' => 'TriggerFields is not set')
            ]),400);
        }

        /**
         * If the code makes it to this point, we set up an empty response array with a data field. This will later be
         * used to populate our response back to IFTTT.
         */
        $response_data['data'] = array();

        /**
         * If any record limits were specified from IFTTT, we will only return the amount of items they specified in
         * the limits field.
         */
        $limit = $request_data['limit'];

        /**
         * We check if the restaurant_location field has anything in it, if it does, then we create our latitude and
         * longitude variables that we'll use later. We pass the lat and lon variable through number_format in order
         * to reduce the length of the decimals to 4. i.e. this will take lat 38.2355554 and make it 38.2355. This
         * is needed because the yelp data in our database only has 4 decimals for the gps coordinates.
         */
        if(isset($request_data['triggerFields']['restaurant_location'])){
            $lat = number_format($request_data['triggerFields']['restaurant_location']['lat'], 4);
            $lon = number_format($request_data['triggerFields']['restaurant_location']['lng'],4) ;
        }

        /**
         * Get a list of records based on the restaurant latitude and longitude field that was passes to us earlier.
         */
        $db = $this->container->db->connection();
        $records = $db->select($db->raw("SELECT *, SQRT(POW(69.1 * (latitude - :lat), 2) + POW(69.1 * (:lon - longitude) * COS(latitude / 57.3), 2)) AS distance FROM ifttt_api.restaurant_inspections HAVING distance < .5 ORDER BY distance LIMIT 1"),
            array('lon'=>$lon,'lat'=>$lat));

        /**
         * If records is not empty or 0
         */
        if($records){
            /**
             * For each record
             */
            foreach($records as $record){
                /**
                 * Parse JSON inspections from inspections variable.
                 */
                $inspections = json_decode($record->inspections,true);

                /**
                 * Sort inspections by key, so that they are sorted by descending date.
                 */
                uksort($inspections, function ($a, $b) {
                    $aDate = explode('-', $a);
                    $aDate = $aDate[2] . $aDate[0] . $aDate[1];
                    $bDate = explode('-', $b);
                    $bDate = $bDate[2] . $bDate[0] . $bDate[1];

                    if ($aDate == $bDate) {
                        return 0;
                    }
                    return ($aDate < $bDate) ? 1 : -1;
                });

                /**
                 * If the limit is 1, we only get the first inspection in the list, which from the sort we just did above,
                 * it should be the first item in the array.
                 */
                if($limit === 1){
                    $inspections = (array_slice($inspections, 0, 1));
                }

                /**
                 * If the limit is 0, then clear out the inspections array so that it doesn't return anything.
                 */
                if($limit === 0) {
                    $inspections = array();
                }

                /**
                 * This is where we loop through all the violations within an inspection. All we do here is loop through
                 * it and use array_map to return only the violation descriptions and drop any other fields in the array.
                 * We then add all the violations together to make a list. If there are no violations, then we set the
                 * violations variable to "No Violations".
                 */
                foreach($inspections as $date => $inspection){
                    $violations = array_map(function($violation){
                        return "*" . $violation['description'];
                    },$inspection['violations']);
                    $violations = implode("<br>",$violations);
                    if($violations == null){
                        $violations = "No Violations";
                    }

                    /**
                     * This is where we create our response data array objects that contains the inspections for the
                     * restaurant that IFTTT is requesting the data for.
                     */
                    $response_data['data'][] = array(
                        'id' => $date,
                        'inspection_date' => $date,
                        'restaurant_name' => $record->name,
                        'address' => $record->address,
                        'inspection_score' => $inspection['score'],
                        'violations' => $violations,
                        'meta' => array(
                            'id' => $date,
                            'timestamp' => strtotime(str_replace('-', '/', $date))
                        )
                    );
                }

            }
        }

        /**
         * Return the response_data variable back to IFTTT.
         */
        return $response->withHeader('charset','utf-8')->withJson($response_data,200);
    }

    /**
     * This function validates the restaurant address that is specified by the user when creating an applet.
     */
    function favorite_restaurant_inspections_restaurant_address_validation($request, $response, $args){
        /**
         * Parse JSON data and get latitude and longitude from the value field array.
         */
        $request_data = json_decode($request->getBody()->getContents(), true);
        $lat = number_format($request_data['value']['lat'], 4);
        $lon = number_format($request_data['value']['lng'],4) ;


        /**
         * Query DB with the given longitude and latitude and try to find a record with the closest coordinates.
         */
        $db = $this->container->db->connection();
        $restaurant = $db->select($db->raw("SELECT *, SQRT(POW(69.1 * (latitude - :lat), 2) + POW(69.1 * (:lon - longitude) * COS(latitude / 57.3), 2)) AS distance FROM ifttt_api.restaurant_inspections HAVING distance < .5 ORDER BY distance LIMIT 1"),
            array('lon'=>$lon,'lat'=>$lat));

        /**
         * If the restaurant variable isn't empty or 0.
         */
        if($restaurant){
            /**
             * If there is a restaurant based on the address, We generate response data that will be sent back to IFTTT
             * telling them that the validation is valid.
             */
            $responseData['data'] = [
                'valid' => true
            ];
        } else {
            /**
             * If there is no restaurant with that address, we generate the response data to tell IFTTT that the data
             * is invalid. Along with a message to let the user know that we could not find a restaurant based on the
             * address given.
             */
            $responseData['data'] = [
                'valid' => false,
                'message' => 'Could not find restaurant at this address'
            ];
        }

        /**
         * Return our response.
         */
        return $response->withHeader('charset','utf-8')->withJson($responseData,200);
    }
}