<?php

namespace Src\Controllers;

use KzykHys\CsvParser\CsvParser;
use Slim\Views\Twig as View;
use Illuminate\Database\Capsule\Manager as Capsule;

class NearbyDevelopmentInspectionController extends Controller
{

    /**
     * This function imports the NearbyDevelopment development_records data from data.louisvilleky.gov's yelp dataset.
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
            $this->container->logger->info('Starting NearbyDevelopment development_records import task');
            $activePermitsFileUrl = "http://lky-open-data.s3.amazonaws.com/ConstructionReview/ActivePermits.csv";

            /**
             * Pull the businesses list CSV into a string via file_get_contents
             */
            $activePermitsCSV = file_get_contents($businessesFileURL);

            /**
             * Create the CSV parser using the CSV string that we generated earlier, set the offset to 1 since our
             * first line in the CSV is our column headers.
             */
            $parser = CsvParser::fromString($activePermitsCSV,['offset'=>1]);

            /**
             * Create an empty NearbyDevelopments variable. This variable will be used inside the foreach loop to generate
             * our NearbyDevelopments array.
             */
            $NearbyDevelopments = null;

            /**
             * Iterate through the CSV parser and start seeding the $NearbyDevelopments variable that we created earlier with
             * each NearbyDevelopments information. Please note that we use the NearbyDevelopments business ID as the key/index for
             * each record, this way we can later match the development_records and violations back to the correct business
             * via this index.
             */
            foreach ($parser as $record) {
                $NearbyDevelopments[$record[0]] = array(
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
            unset($activePermitsCSV);



            /**
             * We delete all the records in the NearbyDevelopment development_records table.
             */
            $this->container->db->table('NearbyDevelopment_records')->delete();

            /**
             * Iterate through NearbyDevelopments array.
             */
            foreach ($NearbyDevelopments as $index => $NearbyDevelopment){
                /**
                 * JSON encode all the inspection records before inserting into database.
                 */
                $NearbyDevelopments[$index]['development_records'] = json_encode($NearbyDevelopment['development_records']);

                /**
                 * Once the development_records data has been encoded into JSON, we insert the record into the database.
                 */
                $this->container->db->table('NearbyDevelopment_records')->insert($NearbyDevelopments[$index]);
            }

            /**
             * Log that we've finished the NearbyDevelopment inspection import task
             */
            $this->container->logger->info('Finished NearbyDevelopment inpections import task');

            return $response->withStatus(200);
        } catch (\Exception $e) {
            /**
             * If anything went wrong and exceptions were thrown, catch them and log the issue. Return a 500 error back
             * to the user/cron.
             */
            $this->container->logger->error('Could not import NearbyDevelopment development_records. Error: ' . $e->getMessage());
            return $response->withStatus(500);
        }
    }

    /**
     * When IFTTT queries our server and passes it along an address to get development_records for, this function queries the
     * database for that particular NearbyDevelopment by address and passes back all development_records to IFTTT.
     */
    function favorite_NearbyDevelopment_records($request, $response, $args){

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
         * We check if the NearbyDevelopment_location field has anything in it, if it does, then we create our latitude and
         * longitude variables that we'll use later. We pass the Latitude and Longitude variable through number_format in order
         * to reduce the length of the decimals to 4. i.e. this will take Latitude 38.2355554 and make it 38.2355. This
         * is needed because the yelp data in our database only has 4 decimals for the gps coordinates.
         */
        if(isset($request_data['triggerFields']['NearbyDevelopment_location'])){
            $Latitude = number_format($request_data['triggerFields']['NearbyDevelopment_location']['Latitude'], 4);
            $Longitude = number_format($request_data['triggerFields']['NearbyDevelopment_location']['Longitude'],4) ;
        }

        /**
         * Get a list of records based on the NearbyDevelopment latitude and longitude field that was passes to us earlier.
         */
        $db = $this->container->db->connection();
        $records = $db->select($db->raw("SELECT *, SQRT(POW(69.1 * (latitude - :lat), 2) + POW(69.1 * (:Longitude - longitude) * COS(latitude / 57.3), 2)) AS distance FROM ifttt_api.NearbyDevelopment_records HAVING distance < .5 ORDER BY distance LIMIT 1"),
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
                 * Parse JSON development_records from development_records variable.
                 */
                $development_records = json_decode($record->development_records,true);

                /**
                 * Sort development_records by key, so that they are sorted by descending date.
                 */
                uksort($development_records, function ($a, $b) {
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
                    $development_records = (array_slice($development_records, 0, 1));
                }

                /**
                 * If the limit is 0, then clear out the development_records array so that it doesn't return anything.
                 */
                if($limit === 0) {
                    $development_records = array();
                }

                /**
                 * This is where we loop through all the violations within an inspection. All we do here is loop through
                 * it and use array_map to return only the violation descriptions and drop any other fields in the array.
                 * We then add all the violations together to make a list. If there are no violations, then we set the
                 * violations variable to "No Violations".
                 */
                foreach($development_records as $date => $inspection){
                    $violations = array_map(function($violation){
                        return "*" . $violation['description'];
                    },$inspection['violations']);
                    $violations = implode("<br>",$violations);
                    if($violations == null){
                        $violations = "No Violations";
                    }

                    /**
                     * This is where we create our response data array objects that contains the development_records for the
                     * NearbyDevelopment that IFTTT is requesting the data for.
                     */
                    $response_data['data'][] = array(
                        'id' => $date,
                        'inspection_date' => $date,
                        'NearbyDevelopment_name' => $record->name,
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
     * This function validates the NearbyDevelopment address that is specified by the user when creating an applet.
     */
    function favorite_NearbyDevelopment_records_NearbyDevelopment_address_validation($request, $response, $args){
        /**
         * Parse JSON data and get latitude and longitude from the value field array.
         */
        $request_data = json_decode($request->getBody()->getContents(), true);
        $Latitude = number_format($request_data['value']['lat'], 4);
        $Longitude = number_format($request_data['value']['lng'],4) ;


        /**
         * Query DB with the given longitude and latitude and try to find a record with the closest coordinates.
         */
        $db = $this->container->db->connection();
        $NearbyDevelopment = $db->select($db->raw("SELECT *, SQRT(POW(69.1 * (latitude - :lat), 2) + POW(69.1 * (:Longitude - longitude) * COS(latitude / 57.3), 2)) AS distance FROM ifttt_api.NearbyDevelopment_records HAVING distance < .5 ORDER BY distance LIMIT 1"),
            array('lon'=>$lon,'lat'=>$lat));

        /**
         * If the NearbyDevelopment variable isn't empty or 0.
         */
        if($NearbyDevelopment){
            /**
             * If there is a NearbyDevelopment based on the address, We generate response data that will be sent back to IFTTT
             * telling them that the validation is valid.
             */
            $responseData['data'] = [
                'valid' => true
            ];
        } else {
            /**
             * If there is no NearbyDevelopment with that address, we generate the response data to tell IFTTT that the data
             * is invalid. Along with a message to let the user know that we could not find a NearbyDevelopment based on the
             * address given.
             */
            $responseData['data'] = [
                'valid' => false,
                'message' => 'Could not find NearbyDevelopment at this address'
            ];
        }

        /**
         * Return our response.
         */
        return $response->withHeader('charset','utf-8')->withJson($responseData,200);
    }
}
