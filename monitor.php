<?php

    /**
     *  Simple PHP Site Monitor
     *  v.0.0.1
     *  @require php 5.5
     *
     *  @usage php monitor.php mail@domain.ru[,mail2@domain.ru,...] http://webhook.com/do[,http://webhook.com/do2..]
     *  @todo head/get requests
     *  @todo Message templates
     */

    # Setting time limit
    ini_set('max_execution_time', 0);

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
     * Monitoring error callback function
     *
     * @param  array $log_arr information array
     * @param  string $result Request result
     * @return void
     */
    function    _callback($log_arr, $result)
    {
        global $argv;

        if ( ! function_exists('list2array') ) {
            function list2array($list)
            {
                $_array = array();

                if(strpos($list, ',') !== FALSE) {
                    $_array = array_map('trim', explode(',', $list));
                } else {
                    $_array[] = $list;
                }

                return $_array;

            }
        }

        try {

            $result_arr = array(
                'url'       => $log_arr['url'],
                'ip'        => $log_arr['primary_ip'],
                'http_code' => $log_arr['http_code'],
                'error'     => $log_arr['curl_error'],
                'time_ms'      => array(
                    'lookup'    => round(1000 * $log_arr['namelookup_time'] ),
                    'connect'   => round(1000 * ($log_arr['connect_time']       - $log_arr['namelookup_time']) ),
                    'ssl'       => round(1000 * ($log_arr['appconnect_time']    - $log_arr['connect_time']) ),
                    //'redirect'  => round(1000 * $log_arr['redirect_time'] ),
                    'request'   => round(1000 * ($log_arr['starttransfer_time'] - $log_arr['pretransfer_time']) ),
                    'response'  => round(1000 * ($log_arr['total_time']         - $log_arr['starttransfer_time']) ),
                    'total'     => round(1000 * $log_arr['total_time'] )
                )
            );

            $_message = json_encode($result_arr, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
            //$_message = var_export($result_arr, TRUE);
            //$_message = implode("\r\n", $result_arr);

            if( ! empty($argv[1]))
            {
                $emails = list2array($argv[1]);
                foreach ($emails as $key => $email) {
                    mail($email, "Monitoring: " . $log_arr['url'], $_message);
                    _echo("MAIL: $email");
                }
            }

            if( ! empty($argv[2]) ) {

                $webhooks = list2array($argv[2]);

                foreach ($webhooks as $key => $webhook) {

                    //$webhook = str_replace('###message###', $_message, $webhook);
                    $webhook = str_replace('###message###', urlencode($_message), $webhook);
                    $ch = curl_init($webhook);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                    $body = curl_exec($ch);
                    curl_close($ch);

                    _echo("WEBHOOK: $webhook");

                    # TODO: response checks !

                    usleep(1000000 / 2);
                }
            }


        } catch (Exception $e) {

            _echo($e->getMessage());

        }
    }

    /**
     * Monitoring error log function
     *
     * @param  array $log_arr information array
     * @return void
     */
    function    _log($log_arr, $result)
    {
        if( ! is_array($log_arr))
            return;

        $fp_log                  = @fopen(PATH_LOG, 'a+');

        if( ! $fp_log)
            return;

        $result_arr             = array(
            date("Y.m.d H:i:s"),
            $log_arr['url'],
            $log_arr['primary_ip'],
            $log_arr['http_code'],
            $log_arr['total_time'],
            $log_arr['curl_error']

        );

        $result_str             = implode("\t", $result_arr) . PHP_EOL;

        @fwrite($fp_log, $result_str);
        @fclose($fp_log);
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

            $line           = trim($line);

            /**
             * Skip if #
             */
            if($line[0] != '#')
                $servers[]  = $line;

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
        # IPV4 ONLY
        # CURLOPT_IPRESOLVE is available since curl 7.10.8
        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);

        $result = curl_exec($ch);

        $curl_info                      = curl_getinfo($ch);
        // PHP 5.5+ only
        $curl_info['appconnect_time']   = curl_getinfo($ch, CURLINFO_APPCONNECT_TIME);

        if( ! curl_errno($ch) && $curl_info['http_code'] == 200) {

            @touch(PATH_LOG);
            _echo($curl_info['url'] . " - OK");

        } else {

            $curl_info['curl_error'] = curl_error($ch);

            _log($curl_info, $result);
            _callback($curl_info, $result);

            _echo($curl_info['url'] . " - ERROR");

        }

        curl_close($ch);

    }

    _echo(date("Y.m.d H:i:s"));
?>
