<?php

class OutlookController extends BaseController{


    public static function authorize(){


        //INITIALIZE VARS
        if(!isset($_GET['code'])){
            return Redirect::to('/outlook');
        }

        else {
            $auth_code = $_GET['code'];
            $tokens = oAuthService::getTokenFromAuthCode($auth_code, Request::root().'/outlook');

            //HAVE TOKEN
            if ($tokens['access_token']) {


                Session::put('access_token', $tokens['access_token']);

                // Get the user's email from the ID token
                $user_email = oAuthService::getUserEmailFromIdToken($tokens['id_token']);
                Session::put('user_email', $user_email);

                return Redirect::to('/outlooksync');

            } //ERROR NO TOKEN
            else {

                return Redirect::to(oAuthService::getLoginUrl(Request::root().'/outlook'));

            }
        }

    }




    public function logout(){
        Session::forget('access_token');

        return Redirect::to('/profile');
    }


    /**
     * Here we'll only actually show a "loading" page which will redirect via js to the actual sync page.
     * @return mixed
     */
    public function sync(){

        return View::make('outlook-loading');

    }

    public function realSync(){


        //INITIALIZE VARS
        $loggedIn = !is_null(Session::get('access_token'));

        //NOT LOGGED IN REDIRECT TO OUTLOOK
        if (!$loggedIn) {

            return Redirect::to(oAuthService::getLoginUrl(Request::root().'/outlook'));

        }

        //LOGGED IN THEN PULL DATA
        else {

            //FIRST HTTP REQUEST - get initial events and also set page link to session
            if(is_null(Session::get('outlook_page_link'))){

                $events = OutlookService::getEvents(Session::get('access_token'), Session::get('user_email'));

                //ERROR - FOR SOME REASON STILL NOT LOGGED IN?
                //todo: The following error handling really sucks.  Do something better there.
                if (array_key_exists('errorNumber', $events)) {

                    if ($events['errorNumber'] >= '400') {

                        Session::forget('access_token');

                        return Redirect::to(oAuthService::getLoginUrl(Request::root().'/outlook'));

                    }

                } //SUCCESS
                else {

                    self::insertEvents($events);

                    //set session increment
                    Session::put('outlook_counter', 0);

                    //hit the request again
                    return Redirect::to('/outlooksync');

                }
            }

            //NOT FIRST REQUEST- session vars have been set so we're making a separate http request
            else {

                //check incrementor (if less than 1000 events/7 requests) and there's another page link
                if (Session::get('outlook_counter') < 10 && Session::get('outlook_page_link')) {

                    //get next 150 events
                    $events = OutlookService::getEvents(Session::get('access_token'), Session::get('user_email'), Session::get('outlook_page_link'));

                    //insert
                    self::insertEvents($events);

                    Session::put('outlook_counter', Session::get('outlook_counter')+1);

                    //hit the request again
                    return Redirect::to('/outlooksync');

                }

                //we've hit the request enough times to have more than 1000 events
                else{

                    //remove session vars
                    Session::forget('outlook_counter');
                    Session::forget('outlook_page_link');

                    //go to calendar view
                    return Redirect::to('/calendar');

                }

            }
        }

    }




    public static function convertDateTimeFormat($toBeConvertedDateTime){

        $date = new DateTime($toBeConvertedDateTime);
        return $date->format('Y-m-d H:i:s');

    }




    public static function insertEvents($events){

        //shoot events to DB
        foreach ($events as $event) {

            //todo: determine if all day event

            //check existing listing count
            $existingListingCount = Listing::where('external_id',$event['Id'])->
            where('seller_id',Sentry::getUser()->id)->
            where('external_type','outlook-calendar')->
            withTrashed()->
            count();

            //check that event does not already exist in db
            if ($existingListingCount < 1) {


                //1: attempt to the most relevant email associated with this event that is NOT the user's email
                //initialize vars
                $listingData = array();

                //email
                if (isset($event['event_main_contact_email'])) {
                    $listingData['contact_email'] = $event['event_main_contact_email'];
                }

                //user id
                $listingData['seller_id'] = Sentry::getUser()->id;

                //listing type
                $listingData['listing_type'] = 'calendarevent';

                //listing title
                $listingData['listing_title'] = $event['Subject'];

                //event start datetime
                $listingData['event_at'] = self::convertDateTimeFormat($event['Start']['DateTime']);

                //outlook calendar event id
                $listingData['external_id'] = $event['Id'];

                //calendar type
                $listingData['external_type'] = 'outlook-calendar';

                //serialized whole event
                $listingData['internal_data'] = serialize($event);

                //event contact full name
                if (isset($event['event_main_contact_fullname'])) {
                    $listingData['contact_name'] = $event['event_main_contact_fullname'];
                }

                //event contact cell phone number
                if (isset($event['event_main_contact_mobilephone1'])) {
                    $listingData['contact_phone1'] = $event['event_main_contact_mobilephone1'];
                }

                //event contact office phone number
                if (isset($event['event_main_contact_businessphone'])) {
                    $listingData['contact_phone2'] = $event['event_main_contact_businessphone'];
                }

                //event contact job title
                if (isset($event['event_main_contact_jobtitle'])) {
                    $listingData['contact_title'] = $event['event_main_contact_jobtitle'];
                }

                //event contact company id
                if (isset($event['event_main_contact_companyname'])) {
                    $listingData['company_id'] = Company::get($event['event_main_contact_companyname'])->id;
                }

                //event contact job title id
                if (isset($event['event_main_contact_jobtitle'])) {
                    $listingData['title_id'] = Title::get($event['event_main_contact_jobtitle'])->id;
                }


                //insert
                Listing::create($listingData);

            }


        }



    }


}