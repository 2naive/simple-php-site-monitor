<?php

    /**
     *  Simple PHP Site Monitor
     *  v.0.0.1
     *
     *  @usage php monitor.php mail@domain.ru 79261234567 sms_ru-api-key
     */

    # Setting time limit
    ini_set('max_execution_time',0);

    # Setting current paths
    define('PATH',              dirname(__FILE__));
    define('PATH_LOG',          PATH . DIRECTORY_SEPARATOR . 'error.log');
    define('PATH_LIST',         PATH . DIRECTORY_SEPARATOR . 'server.list');

    # Setting useragent
    define('USERAGENT',         'Monitoring Bot');

    # Setting request timeout
    define('TIMEOUT',           10);

    # Disable ECHO ?
    define('ECHO_OFF',          FALSE);

    # Init vars
    $servers                    = array();

    /**
     * Monitoring error log function
     *
     * @param  array $log_arr information array
     * @return void
     */
    function    _log($log_arr)
    {
        if( ! is_array($log_arr))
            return;

        $fp_log                  = @fopen(PATH_LOG, 'a+');

        if( ! $fp_log)
            return;

        $result_arr             = array(
            date("Y.m.d H:i:s"),
            $log_arr['url'],
            $log_arr['http_code'],
            $log_arr['total_time'],
            $log_arr['curl_error']

        );

        $result_str             = implode("\t", $result_arr) . PHP_EOL;

        @fwrite($fp_log, $result_str);
        @fclose($fp_log);
    }

    /**
     * Monitoring error callback function
     *
     * @param  array $log_arr information array
     * @return void
     */
    function    _callback($log_arr)
    {
        global $argv;

        try {

            $result_arr = array(
                $log_arr['url'],
                $log_arr['http_code'],
                (string) round($log_arr['total_time']),
                $log_arr['curl_error']
            );

            if( ! empty($argv[1]))
            {
                mail($argv[1], "Monitoring: " . $log_arr['url'], implode("\r\n", $result_arr));
            }

            if( ! empty($argv[2]) &&  ! empty($argv[3])) {

                $ch = curl_init("http://sms.ru/sms/send");
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                curl_setopt($ch, CURLOPT_POSTFIELDS, array(

                    "api_id"    =>  $argv[2],
                    "to"        =>  $argv[3],
                    "text"      =>  implode(' ', $result_arr)

                ));
                $body = curl_exec($ch);
                curl_close($ch);

                # TODO: response checks !

            }


        } catch (Exception $e) {

            _echo($e->getMessage());

        }
    }

    function    _echo($str)
    {
        if(ECHO_OFF == FALSE)
            echo $str . PHP_EOL;
    }


    # Starting
    _echo(date("Y.m.d H:i:s"));

    # Reading server list file
    $fp_list                    = fopen(PATH_LIST, 'r');

    if ( ! $fp_list) {

        # If file read error - throw exception
        throw new Exception("File open error: " . PATH_LIST, 1);

    } else {

        # Reading servers list to monitor
        # One per line
        while (($line = fgets($fp_list)) !== false) {

            $line               = trim($line);
            $servers[]          = $line;

        }

    }

    foreach ($servers as $id => $server) {

        # Checking
        # Init cURL
        $ch = curl_init();

        # Setting cURL options
        #  URL to check
        curl_setopt($ch, CURLOPT_URL, $server);
        #  return headers
        curl_setopt($ch, CURLOPT_HEADER, TRUE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        #  perform HTTP HEAD request
        curl_setopt($ch, CURLOPT_NOBODY, true);
        #  response timeout
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, TIMEOUT);
        #  useragent
        curl_setopt($ch, CURLOPT_USERAGENT, USERAGENT);
        #  follow redirects
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

        curl_exec($ch);

        $curl_info               = curl_getinfo($ch);

        if( ! curl_errno($ch) && $curl_info['http_code'] == 200) {

            @touch(PATH_LOG);
            _echo($curl_info['url'] . " - OK");

        } else {

            $curl_info['curl_error'] = curl_error($ch);

            _log($curl_info);
            _callback($curl_info);

            _echo($curl_info['url'] . " - ERROR");

        }

        curl_close($ch);

    }

    _echo(date("Y.m.d H:i:s"));
?>
