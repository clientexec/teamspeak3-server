<?php

require_once 'library/CE/NE_MailGateway.php';
require_once 'plugins/server/teamspeak3/class.teamspeak3server.php';
require_once 'modules/admin/models/ServerPlugin.php';
/**
* @package Plugins
*/
class PluginTeamspeak3 extends ServerPlugin
{
    public $usesPackageName = true;
    /*****************************************************************/
    // function getVariables - required function
    /*****************************************************************/
    function getVariables(){
        /* Specification
              itemkey     - used to identify variable in your other functions
              type        - text,textarea,yesno,password
              description - description of the variable, displayed in ClientExec
              encryptable - used to indicate the variable's value must be encrypted in the database
        */


        $variables = array (
                   /*T*/"Name"/*/T*/ => array (
                                        "type"          => "hidden",
                                        "description"   => "Used By CE to show plugin - must match how you call the action function names",
                                        "value"         => "Teamspeak 3"
                                       ),
                   /*T*/"Description"/*/T*/ => array (
                                        "type"          => "hidden",
                                        "description"   => /*T*/"Description viewable by admin in server settings"/*/T*/,
                                        "value"         => /*T*/"Teamspeak 3 voice server integration.  Note: The custom field settings are used to hold information about the clients server.  Please create these fields in admin->custom fields->packages first.  The package name on server fields for each package hold the slot count.  Suspending a server sets the slot count to 0."/*/T*/
                                       ),
                   /*T*/"Username"/*/T*/ => array (
                                        "type"          => "text",
                                        "description"   => /*T*/"Username used to connect to server"/*/T*/,
                                        "value"         => ""
                                       ),
                   /*T*/"Password"/*/T*/ => array (
                                        "type"          => "password",
                                        "description"   => /*T*/"Password used to connect to server"/*/T*/,
                                        "value"         => "",
                                        "encryptable"   => true
                                       ),
                   /*T*/"Starting Teamspeak Port Number"/*/T*/ => array(
                                        "type"          => "text",
                                        "description"   => /*T*/"Enter the starting teamspeak port number you'd like to use.  If the port is already in use it will use the next available port."/*/T*/,
                                        "value"         => "8767"
                                        ),
                   /*T*/"Client Port Custom Field"/*/T*/ => array(
                                        "type"          => "text",
                                        "description"   => /*T*/"Enter the name of the package custom field that will hold the client teamspeak port number."/*/T*/,
                                        "value"         => ""
                                        ),
                   /*T*/"Admin Token Custom Field"/*/T*/ => array(
                                        "type"          => "text",
                                        "description"   => /*T*/"Enter the name of the package custom field that will hold the client teamspeak admin token."/*/T*/,
                                        "value"         => ""
                                        ),
                   /*T*/"Default Server Name"/*/T*/ => array(
                                        "type"          => "text",
                                        "description"   => /*T*/"Enter the default name that the server should be created with."/*/T*/,
                                        "value"         => "Teamspeak 3 Server"
                                        ),
                   /*T*/"Actions"/*/T*/ => array (
                                        "type"          => "hidden",
                                        "description"   => /*T*/"Current actions that are active for this plugin per server"/*/T*/,
                                        "value"         => "Create,Delete,Suspend,UnSuspend"
                                       )
        );
        return $variables;
    }

    function create($args)
    {
        if (  $args['package']['name_on_server'] == null                            ||
                $args['server']['variables']['plugin_teamspeak3_Client_Port_Custom_Field'] == ""        ||
                $args['server']['variables']['plugin_teamspeak3_Admin_Token_Custom_Field']  == ""
           ) throw new CE_Exception ("Team Speak plugin not setup properly");
    	$user = $args['server']['variables']['plugin_teamspeak3_Username'];
    	$pass = $args['server']['variables']['plugin_teamspeak3_Password'];
    	$slotcount = $args['package']['name_on_server'];
    	$servername = $args['server']['variables']['plugin_teamspeak3_Default_Server_Name'];
    	if ($servername == '') $servername = 'Teamspeak 3 Server';

    	$package = new UserPackage($args['package']['id'], $this->user);
    	$port = "";
    	$clientpass = "";
    	$port = $package->getCustomField($args['server']['variables']['plugin_teamspeak3_Client_Port_Custom_Field'], CUSTOM_FIELDS_FOR_PACKAGE);

    	$tsServer = new Teamspeak3Server(
                                       $args['server']['variables']['ServerHostName'],
                                       $args['server']['variables']['plugin_teamspeak3_Username'],
                                       $args['server']['variables']['plugin_teamspeak3_Password']
    	                               );
    	$return = $tsServer->connect();
        if (is_a($return, 'CE_Error')) {
            $tsServer->disconect();
            throw new CE_Exception($return);
        }

        /* If a port is already defined then ensure it's available,
           otherwise find the next available port. */
    	if ($port != "") {
        	if (!$tsServer->checkPortAvailability($port)) {
        	    $tsServer->disconect();
        	    throw new CE_Exception('Port '. $port.' is not available.');
        	}
    	} else {
    	    $portList = $tsServer->getPortList();
    	    $currentPort = $args['server']['variables']['plugin_teamspeak3_Starting_Teamspeak_Port_Number'];
    	    while (true) {
    	        if (!in_array($currentPort, $portList)) {
    	            $port = $currentPort;
    	            break;
    	        }
    	        $currentPort++;
    	    }
    	    $package->setCustomField($args['server']['variables']['plugin_teamspeak3_Client_Port_Custom_Field'], $port, CUSTOM_FIELDS_FOR_PACKAGE);
    	}
        $return = $tsServer->add(
                        $port,
                        $servername,
                        $args['package']['name_on_server']
                      );

        $tsServer->disconect();

        if (is_a($return, 'CE_Error')) throw new CE_Exception($return);

        // store the admin token generated
        $package->setCustomField($args['server']['variables']['plugin_teamspeak3_Admin_Token_Custom_Field'], $return[1], CUSTOM_FIELDS_FOR_PACKAGE);
        return;
    }

    function delete($args)
    {
        if (    $args['package']['name_on_server'] == null                            ||
                $args['server']['variables']['plugin_teamspeak3_Client_Port_Custom_Field'] == ""        ||
                $args['server']['variables']['plugin_teamspeak3_Admin_Token_Custom_Field'] == ""
           ) throw new CE_Exception ("Team Speak plugin not setup properly");

    	$package = new UserPackage($args['package']['id'], $this->user);

    	$port = "";
    	$port = $package->getCustomField($args['server']['variables']['plugin_teamspeak3_Client_Port_Custom_Field'], CUSTOM_FIELDS_FOR_PACKAGE);
    	if ($port == "") return;

        $tsServer = new Teamspeak3Server(
                                       $args['server']['variables']['ServerHostName'],
                                       $args['server']['variables']['plugin_teamspeak3_Username'],
                                       $args['server']['variables']['plugin_teamspeak3_Password']
    	                               );
        $return = $tsServer->connect();
        if (is_a($return, 'CE_Error')) {
            $tsServer->disconect();
            throw new CE_Exception($return);
        }

        $return = $tsServer->delete($port);
        $tsServer->disconect();
        return;
    }

    function update($args)
    {
        if (    $args['package']['name_on_server'] == null                            ||
                $args['server']['variables']['plugin_teamspeak3_Client_Port_Custom_Field'] == ""        ||
                $args['server']['variables']['plugin_teamspeak3_Admin_Token_Custom_Field'] == ""
           ) throw new CE_Exception ("Team Speak plugin not setup properly");

    	$slotcount = $args['package']['name_on_server'];
    	$package = new UserPackage($args['package']['id'], $this->user);

    	$port = "";
    	$port = $package->getCustomField($args['server']['variables']['plugin_teamspeak3_Client_Port_Custom_Field'], CUSTOM_FIELDS_FOR_PACKAGE);
    	if ($port == "") return;

    	if (isset($args['changes']['package'])) {
        	$tsServer = new Teamspeak3Server(
                                           $args['server']['variables']['ServerHostName'],
                                           $args['server']['variables']['plugin_teamspeak3_Username'],
                                           $args['server']['variables']['plugin_teamspeak3_Password']
        	                               );
            $return = $tsServer->connect();
            if (is_a($return, 'CE_Error')) {
                $tsServer->disconect();
                throw new CE_Exception($return);
            }
            $return = $tsServer->update($port, $args['package']['name_on_server']);
            $tsServer->disconect();
            return;
       }
    }

    function suspend($args) {
        if (    $args['package']['name_on_server'] == null                            ||
                $args['server']['variables']['plugin_teamspeak3_Client_Port_Custom_Field'] == ""        ||
                $args['server']['variables']['plugin_teamspeak3_Admin_Token_Custom_Field'] == ""
           ) throw new CE_Exception ("Team Speak plugin not setup properly");

    	$package = new UserPackage($args['package']['id'], $this->user);

    	$port = "";
    	$port = $package->getCustomField($args['server']['variables']['plugin_teamspeak3_Client_Port_Custom_Field'], CUSTOM_FIELDS_FOR_PACKAGE);
    	if ($port == "") return;
    	$tsServer = new Teamspeak3Server(
                                       $args['server']['variables']['ServerHostName'],
                                       $args['server']['variables']['plugin_teamspeak3_Username'],
                                       $args['server']['variables']['plugin_teamspeak3_Password']
    	                               );
        $return = $tsServer->connect();
        if (is_a($return, 'CE_Error')) {
            $tsServer->disconect();
            throw new CE_Exception($return);
        }
        $return = $tsServer->suspend($port);
        $tsServer->disconect();
        return;
    }

    function unsuspend($args) {
        if (    $args['package']['name_on_server'] == null                            ||
                $args['server']['variables']['plugin_teamspeak3_Client_Port_Custom_Field'] == ""        ||
                $args['server']['variables']['plugin_teamspeak3_Admin_Token_Custom_Field'] == ""
           ) throw new CE_Exception ("Team Speak plugin not setup properly");

    	$package = new UserPackage($args['package']['id'], $this->user);
    	$port = "";
    	$port = $package->getCustomField($args['server']['variables']['plugin_teamspeak3_Client_Port_Custom_Field'], CUSTOM_FIELDS_FOR_PACKAGE);
    	if ($port == "") return;
    	$tsServer = new Teamspeak3Server(
                                       $args['server']['variables']['ServerHostName'],
                                       $args['server']['variables']['plugin_teamspeak3_Username'],
                                       $args['server']['variables']['plugin_teamspeak3_Password']
    	                               );
        $return = $tsServer->connect();
        if (is_a($return, 'CE_Error')) {
            $tsServer->disconect();
            throw new CE_Exception($return);
        }
        $return = $tsServer->unsuspend($port);
        $tsServer->disconect();
        return $return;
    }

    function doCreate($args)
    {
        $userPackage = new UserPackage($args['userPackageId']);
        $this->create($this->buildParams($userPackage));
        return 'Package has been created.';
    }

    function doSuspend($args)
    {
        $userPackage = new UserPackage($args['userPackageId']);
        $this->suspend($this->buildParams($userPackage));
        return 'Package has been suspended.';
    }

    function doUnSuspend($args)
    {
        $userPackage = new UserPackage($args['userPackageId']);
        $this->unsuspend($this->buildParams($userPackage));
        return 'Package has been unsuspended.';
    }

    function doDelete($args)
    {
        $userPackage = new UserPackage($args['userPackageId']);
        $this->delete($this->buildParams($userPackage));
        return 'Package has been deleted.';
    }
}
?>
