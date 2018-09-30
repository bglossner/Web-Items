
<?php
	if ($_SERVER['REQUEST_METHOD'] != 'POST') {
		?>
		<html>
			<head>
				<script type="text/javascript">
					function startFunc() {
						//console.log("hi");
						window.open(<?php echo "'/mainpage.html'"; ?>, "_self");
					}
					window.onload = startFunc;
				</script>
			</head>
			<body>
			</body>
		</html>
		<?php
	}
	else {
		//die(json_encode($_SERVER['HTTP_REFERER']));
		//Function uses XOR on every byte of php structure
		function convert_xor($arr, $bytelen, $destination) {
			$holder = 0;
			foreach($arr as $key => $value) {
				if($key != "data") {
					$holder ^= $value;
				}
				//For the data part of the structure (different than the wrapper)
				else if($key == "data") {
					//Same process as Wrapper
					foreach($arr["data"] as $datakey => $datavalue) {
						if(($datakey == "data2") && ($arr["data"]["opcode1"] == 1) && ($destination == 0x18)) {
							//echo "\nHHHHH\n";
							$str = $arr["data"]["data2"]; //The Payload
							for($i = 0; $i < $bytelen; $i++) {
								$holder ^= ord($str[$i]); //Converts the string into the ascii numerical value
							}
							break;
						}
						else {
							$holder ^= $datavalue;
						}
					}
				}
			}
			return $holder;
		}
		
		//Array that specifies the LEDs in the correct order (UNUSED)
		$mapForLeds = [
						"", "400HZ", "DC", "LSN", "TLK", "VOLTAGE", "NORMAL", "STORE", "5V",
							"T1", "PCT_DEV", "RESET", "SRQ", "RMT", "60HZ", "STBY", "OPER",
							"CURRENT", "POWER", "SYNCHR", "PF", "PHASE", "T2", "T3", "T4",
							"T5", "T6", "T7", "T8", "T9", "T10", "T11", "FREQ"
					];
		
		//Array that specifies the KEYS in the correct order (USED)
		$mapForKeys = [
						"", "RESET", "DC", "60HZ", "400HZ", "STORE", "NORMAL",
						"PCT_DEV", "VOLTAGE", "RECALL", "SYNCHR", "POWER", "CURRENT", "CLEAR",
						"FREQ", "PHASE", "PF", "7", "8", "9", "VA", "4", "5", "6",
						"MVMA", "1", "2", "3", "WHZ", "0", "DEC", "DEG", "BS", 
						"OPER", "STBY", "UP", "DN"
					];
					
		$mapForModes = [
						"RESET", "1133", "1133_DIFF", "933", "933_DIFF", "933_ICT_50MA", "933_ICT_1A", "933_VCT_2_4V",
						"933_VCT_100MV", "933_EVENT", "928"
					];
		
		//Socket numbers for each daemon
		$moatsd = 0x1A;
		$pmcd = 0x18;
		
		//Default settings for PMCD as destination
		$dataOpcode2 = 0x00;
		$ledDataOpcode = 0x02;
		$destination = $pmcd; //24
		$server = '/var/run/asimp_sockets/'.$destination; //Socket that the pmcd uses for communication
		$client_sock = '/var/run/asimp_sockets/25'; //Socket that www-data (the web server) uses for communication
		$port = 0;
		$clientIP = '10.10.1.93';
		$ledRecOpcode = 3;
		$givenOpcode = 0x00;
		
		//Other general defaults
		$serialBackOpcode = 267;
		$calConstBack = 269;
		$correctDataOpcodes = [$ledRecOpcode, $serialBackOpcode, $calConstBack];
		
		//Actual message we got
		$msg = json_decode($_POST["jsonthing"], false);
		//$msg = "das";
		//$incomingURL = "moatscomms.html";
		$incomingURL = $_SERVER['HTTP_REFERER'];
		
		//Test if its for MOATSd
		if((strpos($incomingURL, "moatscomms.html")) !== FALSE) {
			if(!($_POST["sendpmc"])) {
				$destination = $moatsd; //26
				$dataOpcode2 = 0x01;
				$ledDataOpcode = 0x08;
				$server = '10.10.1.186';
				$port = 31415;
				$ledRecOpcode = 265;
				$correctDataOpcodes[0] = $ledRecOpcode;
				$receivedOpcode = $_POST["opcode"];
				//$receivedOpcode = 12;
				if($receivedOpcode !== null) {
					$givenOpcode = $receivedOpcode;
					//die(json_encode("Rec op: $givenOpcode"));
				}
				else if($msg != "led_check") {
					die(json_encode("No opcode received for MOATSd"));
				}
			}
		}
		
		/*if($_POST["returnDest"] !== null) {
			$returnDest = (int)$_POST["returnDest"];
		}
		else {
			$returnDest = $destination;
		}*/
		
		//$givenOpcode = 10;
		//die(json_encode($givenOpcode));	
		//Define the data type to be sent back
		header("Content-Type: application/json");
		
		//Makes error handling easier
		error_reporting(~E_WARNING);
				
		//Create the socket
		
		//FOR PMCD
		if($destination == 24)
		{
			//Deletes the socket if it exists so it can rebind later
			unlink($client_sock);
			
			if(!($sock = socket_create(AF_UNIX, SOCK_DGRAM, 0)))
			{
				$errorcode = socket_last_error();
				$errormsg = socket_strerror($errorcode);
				 
				die(json_encode("Could not create socket: [$errorcode] $errormsg \n"));
			}
			
			socket_bind($sock, $client_sock);
			//Bind to the socket
			/*if(!(socket_bind($sock, $client_sock))) {
				$errorcode = socket_last_error();
				$errormsg = socket_strerror($errorcode);
				 
				die(json_encode("Could not bind socket: [$errorcode] $errormsg \n"));
			}*/
		}
		//FOR MOATSD
		else if($destination == 26)
		{
			//die(json_encode(print_r($_POST)));
			//die(json_encode($_POST["opcode"]));
			//Create the socket
			if(!($sock = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP)))
			{
				$errorcode = socket_last_error();
				$errormsg = socket_strerror($errorcode);
				 
				die(json_encode("Could not create socket: [$errorcode] $errormsg \n"));
			}
			
			//Bind to the socket
			/*if(!(socket_bind($sock, $clientIP, $port))) {
				$errorcode = socket_last_error();
				$errormsg = socket_strerror($errorcode);
				 
				die(json_encode("Could not bind socket: [$errorcode] $errormsg \n"));
			}*/
		}

		//print_r($_POST);
		/*if(strpos($msg, ":") !== false) {
			echo json_encode("done");
		}
		else {
			echo json_encode("1:".$msg);
		}
		exit();*/
		//$msg = "led_check";
		//$msg = "DC";
		//$msg = "30:";
		//$msg = "WHZ";
		//$msg = "BS";
		$bytelength = strlen($msg); //Number of bytes in the message
		
		//for_routine specifies if it's for a LED status check from the Web Server
		if($msg == "led_check") {
			$for_routine = true;
		}
		else {
			$for_routine = false;
		}
		//Opcode and length are from MSB to LSB (little endian)
		$data = [
				"opcode1" => $givenOpcode, //Value for PMC opcode of data
				"opcode2" => $dataOpcode2, //Opcode of data message
				"lgth1" => 0x00, //Length (in bytes) of the payload
				"lgth2" => 0x00 //Always 0
				];
		
		if($for_routine) {	//for routine LED check
			$bytelength = 0; //No data
			$data["opcode1"] = $ledDataOpcode; //Value in pmcd or moatsd
		}
		else {	//For sending tests
			//Is it a numerical value test?
			$colonpos = strpos($msg, ":");
			$slashpos = strpos($msg, "/");
			
			if($colonpos !== false) {
				$bytelength -= 1;
				$msg = substr($msg, 0, $colonpos);
				/*else{
					//What to do if msg is just wrong
				}*/
				$data["data2"] = $msg;
				$data["opcode1"] = 0x01;
				$data["lgth1"] = $bytelength;
				//echo json_encode("done");
				//exit();
			}
			else if($slashpos !== false)
			{
				if($slashpos == 0) {
					$bytelength = 1;
					$matchingUUT = ["NONE", "1133", "933", "928"];
					$msg = substr($msg, 1);
					$msg = (int)array_search($msg, $matchingUUT);
					if($msg === false) {
						die(json_encode("U.U.T. number did not match any one"));
					}
					else {
						$data["data2"] = $msg;
						$data["opcode1"] = 0x0A;
						$data["lgth1"] = 0x01;
					}
				}
			}
			else if($givenOpcode > 0) {
				$bytelength = 0;
				$data["lgth1"] = 0;
			}
			else {
				if($destination == 26) {
					if($givenOpcode == 0) {
						$data["data2"] = array_search($msg, $mapForModes);
					}
					//echo json_encode("Data PHP: $data['data2']\n");
				}
				else {
					if($msg != "calcons") {
						//opcode2 is still 0
						if($msg == "DEGENT") {
							$msg = "DEG";
						}
						
						$data["data2"] = (int)array_search($msg, $mapForKeys); //Return the index of key press
					}
					else {
						$data["opcode1"] = 12;
						$data["data2"] = 1;
					}
				}

				$bytelength = 1;
				$data["lgth1"] = 0x01;
			}
		}
		
		//echo json_encode($data);
		/*else {
			//What to do if the msg is just wrong?
		}*/
		
		$phpstruc = [
					"sync1" => 0x1e, //Sync Byte
					"sync2" => 0x1e, //Sync Bytes
					"dst" => $destination, //Destination Byte
					"src" => 0x19, //Source Byte
					"opcode1" => 0x22, //App-Specific -- 0x03 -- 802 -- BYTE SWAPPED
					"opcode2" => 0x03, //App-Specific -- 0x22 -- 802
					"lgth1" => 0x00, //Length Byte (at least 4) of whole data message
					"lgth2" => 0x00, //Always 0
					"data" => $data //Data bytes
				];
		$phpstruc["lgth1"] = 4 + $bytelength;
		$phpstruc["checksum"] = convert_xor($phpstruc, $bytelength, $destination); //Used to check that the data isn't corrcupted for the PMCd
		$binstr = null;
		//print_r($phpstruc);
		//die();
		//echo "\nCheckSum: ".$phpstruc["checksum"]."\n";
		//Construct the binary string based phpstruc
		foreach($phpstruc as $key => $value) {
			if($key == "data") {
				foreach($phpstruc["data"] as $datakey => $datavalue) {
					if(($datakey == "data2") && ($phpstruc["data"]["opcode1"] == 1)  && ($destination == 0x18)) {
						$binstr .= $phpstruc["data"]["data2"];
						break;
					}
					$binstr .= pack("C*", $datavalue);
				}
			}
			else {
				$binstr .= pack("C*", $value);
			}
		}
		//echo json_encode(unpack("C*", $binstr));
		
		$errorArr = [];
		$rArray = [];
		
		//Set up with time()
		$initialSecs = time();
		$timeFrame = 5;
		if($_POST["timeframe"] !== NULL) {
			$timeFrame = (int)($_POST["timeframe"]);
		}
		
		$timedout = true;
		$noTimeout = false;
		if($_POST["timeout"] !== NULL) {
			$noTimeout = true;
		}
		
		$reply = '';
		
		//Send the binary string made above to the server socket (pmcd)
		if(!socket_sendto($sock, $binstr, strlen($binstr) , 0 , $server , $port))
		{
			$errorcode = socket_last_error();
			$errormsg = socket_strerror($errorcode);
			 
			die(json_encode("Could not send data: [$errorcode] $errormsg \n"));
		}
		//echo "Sent";
		/* 
		 * --- For milliseconds ---
		 * $x = round(microtime(true) * 1000);
		 * echo substr($x, strlen($x) - 3);
		 */
		 
		 //Array for holding error messages when data is received incorrectly
		
		//Set up for using a seconds timer
		// TODO: use time() instead
		/*$initialSecs = date("s", time());
		$timeFrame = 2; //Change to increase the duration of the timer
		$finalSec = ($initialSecs + $timeFrame) % 60;
		$maxNum = max($initialSecs, $finalSec);
		$minNum = min($initialSecs, $finalSec);
		$lastTime = $initialSecs;
		$fitsRange = (($finalSec - $timeFrame) >= 0);*/
		
		//Start of testing for a response from the pmcd
		while(((time() - $initialSecs) < $timeFrame) || $noTimeout) {
			start_of_loop:
			//Test if data is coming in, but don't wait for it to come in. Data is held as $reply variable
			if(socket_recvfrom ( $sock , $reply , 65536 , MSG_DONTWAIT, $server, $port ) === FALSE) {
				/*$errorcode = socket_last_error();
				$errormsg = socket_strerror($errorcode);
				 
				die("Could not receive data: [$errorcode] $errormsg \n");*/
			}
			
			//Received data
			else if(strlen($reply) > 0){
				//For binary data
				$bytes = array_merge(unpack("C*", $reply));
				//echo "hi";
				//die(json_encode($bytes));
				//$rArray[] = $bytes;
				/*$fn = "/home/pi/getback.txt";
				$file = fopen($fn, "a+");
				foreach($bytes as $tmpTxt) {
					fwrite($file, $tmpTxt."\n");
				}
				fwrite($file, "\n\n\n\n");
				fclose($file);*/
				$opcodeRec = -1;
				//print_r($bytes);
				//echo json_encode($reply);
				//break;
				//Check the reply
				$replyPayload = []; //Array made for each byte of the payload
				
				$blength = count($bytes);
				if($blength > 8) {
					if(($bytes[0] == 30) && ($bytes[1] == 30)) { //Correct Sync Bytes
						if(($bytes[2] == 25) && ($bytes[3] == $destination)) { //Correct Destination and Source
							
							//Bitwise values (16 bit) for the length and for the wrapper opcode
							$opcode = ($bytes[5] << 8) + $bytes[4];
							$length = ($bytes[7] << 8) + $bytes[6];
							
							//Check opcode received
							//Bitwise values (16 bit) for the length and for the wrapper opcode
							$opcode = ($bytes[5] << 8) + $bytes[4];
							$length = ($bytes[7] << 8) + $bytes[6];
							//Check opcode received
							switch($opcode) {
								//AcK received
								case 1024:
									if($length == 0) {
										$opcodeRec = 0;
									}
									else {
										$errorArr[] = "Failed: Wrong wrapper length";
										goto start_of_loop;
									}
									break;
								
								//NAcK received
								case 1025:
									if($length == 0) {
										$opcodeRec = 1;
										$replyPayload[] = $bytes[$blength - 2]; //Should be just one byte
									}
									else {
										$errorArr[] = "Failed: Wrong wrapper length";
										goto start_of_loop;
									}
									break;
								
								//APP SPEC received
								case 802:
									if($length == ($blength - 9)) { //Length check
										if($bytes[10] == ($blength - 13)) { //Data length check
											$opcodeRec = ($bytes[9] << 8) + $bytes[8]; //Opcode for data
											//die(json_encode($correctDataOpcodes));
											if(in_array($opcodeRec, $correctDataOpcodes) === FALSE) {
												$errorArr[] = "Failed: Wrong data opcode";
												goto start_of_loop;
											}
										}
										else {
											$errorArr[] = "Failed: Wrong data length";
											goto start_of_loop;
										}
									}
									else {
										$errorArr[] = "Failed: Wrong wrapper length";
										goto start_of_loop;
									}
									break;
									
								default:
									$errorArr[] = "Failed: Wrong wrapper opcode";
									goto start_of_loop;
									break;
							}
							
							//Do a checksum for data corruption security
							$checksum = 0;
							for($i = 0; $i < $blength; $i++) {
								$checksum ^= $bytes[$i];
							}
							//echo $checksum;
							if(!$checksum) { //Make sure it equals 0
								if($blength > 13) { //Minimum number of bytes with a payload in msg
									for($i = 12; $i < ($blength - 1); $i++) {
										$replyPayload[] = $bytes[$i];
									}
								}
								$timedout = false;
							}
							else { //Corrupted data
								$errorArr[] = "Failed: corrupted data";
								goto start_of_loop;
							}
						}
						else {
							if($bytes[2] != 25) {
								$errorArr[] = "Failed: Wrong destination";
							}
							if($bytes[3] != 24) {
								$errorArr[] = "Failed: Wrong source";
							}
							continue;
						}
					}
					else { //Wrong sync bytes
						continue;
					}
				}
				else { //not enough length
					continue;
				}
				
				//LED status update received
				/*$for_routine && */
				if(($opcodeRec == $ledRecOpcode)) {
					$arrOn = array(); //Initialize array that holds numbers of LEDs that are on
					$counter = 0;
					foreach($replyPayload as $tmpByte) {
						if($tmpByte == 0x00) { //Skip byte entirely
							$counter += 8;
							continue;
						}
						//Go through each bit of byte and test if it's ON (1) or OFF (0)
						for($i = 0; $i < 8; $i++) {
							if(($tmpByte & 0x01) == 1) {
								$arrOn[] = $counter; //Add that LED number to the on array
							}
							$counter++; //Increase the LED number by one
							$tmpByte >>= 1; //Shift the byte to the right
						}
					}
					echo json_encode($arrOn); //Send back the array of which LEDs are on
				}
				else if($opcodeRec == $serialBackOpcode) {
					if(count($replyPayload) == 1) {
						if($replyPayload[0] != -1) {
							echo json_encode($replyPayload[0]);
						}
						else {
							echo json_encode("No serial number connected");
						}
					}
				}
				else if($opcodeRec == $calConstBack) {
					$floatArr = [];
					//print_r($replyPayload);
					for($i = 0; $i < count($replyPayload); $i += 4) {
						$binstr = null;
						for($j = 0; $j < 4; $j++) {
							$binstr .= pack("C*", $replyPayload[$i + $j]);
						}
						$floatVal = (unpack("f", $binstr))[1];
						//$floatVal = (unpack("f", pack("C*", $replyPayload[$i], $replyPayload[$i + 1], 
														//	$replyPayload[$i + 2], $replyPayload[$i + 3])))[1];
						//echo "Float Value: $floatVal\n";
						$floatArr[] = round($floatVal, 6);
					}
					echo json_encode($floatArr);
				}
				else {
					if($opcodeRec == 0) { //Check if AcK or NAcK
						echo json_encode("done"); //AcK
					}
					else {
						echo json_encode("Failed: NAcK Error: ".$replyPayload[0].". See NAcK errors"); //NAcK
					}
				}
				break; //Done with the php script
			}
		}
		
		//Took too long, AKA timed out
		if($timedout) {
			echo json_encode(array($errorArr, "timedout", $rArray)); //Give back any errors before timed out
		}
		//Close and unlink/delete the socket
		socket_close($sock);
		if($destination == 26) {
			unlink($client_sock);
		}
	}
?>

