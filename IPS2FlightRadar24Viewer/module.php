<?
    // Klassendefinition
    class IPS2FlightRadar24Viewer extends IPSModule 
    {
	public function Destroy() 
	{
		//Never delete this line!
		parent::Destroy();
		$this->SetTimerInterval("Timer_1", 0);
		$this->SetTimerInterval("CleanDataArray", 0);
	}  
	    
	// Überschreibt die interne IPS_Create($id) Funktion
        public function Create() 
        {
            	// Diese Zeile nicht löschen.
            	parent::Create();
		$this->RegisterPropertyBoolean("Open", false);
		$this->RegisterPropertyString("IP", "127.0.0.1");
		$this->RegisterPropertyString("Location", '{"latitude":0,"longitude":0}');
		$this->RegisterPropertyInteger("Timer_1", 3);
		$this->RegisterPropertyInteger("CleanDataArray", 60);
		$this->RegisterPropertyInteger("HeightOverNN", 0);
		$this->RegisterTimer("Timer_1", 0, 'IPS2FlightRadar24Viewer_DataUpdate($_IPS["TARGET"]);');
		$this->RegisterTimer("CleanDataArray", 0, 'IPS2FlightRadar24Viewer_CleanDataArray($_IPS["TARGET"]);');
		$this->RequireParent("{3CFF0FD9-E306-41DB-9B5A-9D06D38576C3}");
		
		//Status-Variablen anlegen
		$this->RegisterVariableString("Statistics", "Statistik", "~HTMLBox", 10);
		$this->RegisterVariableString("Aircrafts", "Flugzeuge", "~HTMLBox", 20);
		//$this->RegisterVariableString("Aircrafts_2", "Flugzeuge", "~HTMLBox", 30);
		//$this->RegisterVariableInteger("Messages", "Nachrichten", "", 40);
		
		//$this->RegisterVariableString("Mausefalle", "Mausefalle", "", 100);
		//$this->RegisterVariableString("MausefalleZeit", "MausefalleZeit", "", 110);
		//$this->RegisterVariableString("DataArray", "DataArray", "~TextBox", 120);
		
		$DataArray = array();
		$this->SetBuffer("Data", serialize($DataArray));
		
        }
 	
	public function GetConfigurationForm() 
	{ 
		$arrayStatus = array(); 
		$arrayStatus[] = array("code" => 101, "icon" => "inactive", "caption" => "Instanz wird erstellt"); 
		$arrayStatus[] = array("code" => 102, "icon" => "active", "caption" => "Instanz ist aktiv");
		$arrayStatus[] = array("code" => 104, "icon" => "inactive", "caption" => "Instanz ist inaktiv");
		$arrayStatus[] = array("code" => 202, "icon" => "error", "caption" => "Fehlerhafte Schnittstelle!");
				
		$arrayElements = array(); 
		
		$arrayElements[] = array("name" => "Open", "type" => "CheckBox",  "caption" => "Aktiv");
		$arrayElements[] = array("type" => "Label", "label" => "IP des Flightradar24-Gerätes"); 
		$arrayElements[] = array("type" => "ValidationTextBox", "name" => "IP", "caption" => "IP");
		$arrayElements[] = array("type" => "SelectLocation", "name" => "Location", "caption" => "Region");
		$arrayElements[] = array("type" => "Label", "label" => "Höhe über NN"); 
		$arrayElements[] = array("type" => "IntervalBox", "name" => "HeightOverNN", "caption" => "m");
		$arrayElements[] = array("type" => "Label", "label" => "Zeit nach der Daten ohne Update gelöscht werden"); 
		$arrayElements[] = array("type" => "IntervalBox", "name" => "CleanDataArray", "caption" => "sek");
 		return JSON_encode(array("status" => $arrayStatus, "elements" => $arrayElements)); 		 
 	}       
	   
        // Überschreibt die intere IPS_ApplyChanges($id) Funktion
        public function ApplyChanges() 
        {
            	// Diese Zeile nicht löschen
            	parent::ApplyChanges();
		
		$ParentID = $this->GetParentID();
			
			If ($ParentID > 0) {
				If (IPS_GetProperty($ParentID, 'Host') <> $this->ReadPropertyString('IP')) {
		                	IPS_SetProperty($ParentID, 'Host', $this->ReadPropertyString('IP'));
				}
				If (IPS_GetProperty($ParentID, 'Port') <> 30003) {
		                	IPS_SetProperty($ParentID, 'Port', 30003);
				}
				If (IPS_GetProperty($ParentID, 'Open') <> $this->ReadPropertyBoolean("Open")) {
		                	IPS_SetProperty($ParentID, 'Open', $this->ReadPropertyBoolean("Open"));
				}
				If (substr(IPS_GetName($ParentID), 0, 16) == "Client Socket") {
					IPS_SetName($ParentID, "FlightRadar24 (IPS2FlightRadar24 #".$this->InstanceID.")");
				}
				if(IPS_HasChanges($ParentID))
				{
				    	$Result = @IPS_ApplyChanges($ParentID);
					If ($Result) {
						$this->SendDebug("ApplyChanges", "Einrichtung des Client Socket erfolgreich", 0);
					}
					else {
						$this->SendDebug("ApplyChanges", "Einrichtung des Client Socket nicht erfolgreich!", 0);
					}
				}
			}
		
		
		If ($this->ReadPropertyBoolean("Open") == true) {
			$IP = $this->ReadPropertyString("IP");
			If (filter_var($IP, FILTER_VALIDATE_IP)) {
				$this->CleanDataArray();
				$this->DataUpdate();
				$this->SetStatus(102);
				$this->SetTimerInterval("Timer_1", 1000);
				$this->SetTimerInterval("CleanDataArray", 30 * 1000);
			}
			else {
				Echo "Syntax der IP inkorrekt!";
				$this->SendDebug("ApplyChanges", "Syntax der IP inkorrekt!", 0);
				$this->SetStatus(202);
				$this->SetTimerInterval("Timer_1", 0);
				$this->SetTimerInterval("CleanDataArray", 0);
			}
		}
		else {
			$this->SetStatus(104);
			$this->SetTimerInterval("Timer_1", 0);
			$this->SetTimerInterval("CleanDataArray", 0);
		}	
	}
	
	public function ReceiveData($JSONString) {	
 	    	// Empfangene Daten vom I/O
	    	$Data = json_decode($JSONString);
	    	$Buffer = utf8_decode($Data->Buffer);     
	    	//$this->SendDebug("ReceiveData", $Buffer, 0);
		$Buffer = trim($Buffer, "\x00..\x1F");
		$MessageParts = explode(chr(13), $Buffer);
		
		foreach ($MessageParts as $Message) {
			$Message = trim($Message, "\x00..\x1F");
			//$this->SendDebug("ReceiveData", serialize($Message), 0);
			$SBS1Date = explode(",", $Message);
			If (is_array($SBS1Date) == true) {
				if (IPS_SemaphoreEnter("ReceiveData", 1000)) {
					// Modul Array entpacken
					$DataArray = array();
					$DataArray = unserialize($this->GetBuffer("Data"));
					$MessageType = $SBS1Date[0]; // Message type
					$SessionID = $SBS1Date[2]; // Database Session record number
					$AircraftID = $SBS1Date[3]; // Database Aircraft record number
					$DataArray[$SessionID][$AircraftID]["FlightID"] = $SBS1Date[5];
					$DataArray[$SessionID][$AircraftID]["DateMessageGenerated"] = $SBS1Date[6];
					$DataArray[$SessionID][$AircraftID]["TimeMessageGenerated"] = $SBS1Date[7];
					$TimeParts = explode(".", $SBS1Date[7]);
					$Microseconds = intval($TimeParts[1]) / 1000;
					$Timestamp = strtotime($SBS1Date[6]." ".$SBS1Date[7]);
					$Timestamp = intval($Timestamp) + $Microseconds;
					$DataArray[$SessionID][$AircraftID]["Timestamp"] = $Timestamp;
					$DataArray[$SessionID][$AircraftID]["DateMessageLogged"] = $SBS1Date[8];
					$DataArray[$SessionID][$AircraftID]["TimeMessageLogged"] = $SBS1Date[9];

					switch($MessageType) { // Message type
						case "SEL":
							$this->SendDebug("ReceiveData", "SEL: ".serialize($Message), 0);
							$DataArray[$SessionID][$AircraftID]["HexIdent"] = $SBS1Date[4];
							$DataArray[$SessionID][$AircraftID]["Country"] = $this->GetCountry($SBS1Date[4]);
							$DataArray[$SessionID][$AircraftID]["CallSign"] = $SBS1Date[10];
							break;
						case "ID":
							$this->SendDebug("ReceiveData", "ID: ".serialize($Message), 0);
							$DataArray[$SessionID][$AircraftID]["HexIdent"] = $SBS1Date[4];
							$DataArray[$SessionID][$AircraftID]["Country"] = $this->GetCountry($SBS1Date[4]);
							$DataArray[$SessionID][$AircraftID]["CallSign"] = $SBS1Date[10];
							break;
						case "AIR":
							$this->SendDebug("ReceiveData", "AIR: ".serialize($Message), 0);
							$DataArray[$SessionID][$AircraftID]["TransmissionType"] = "n/v";
							$DataArray[$SessionID][$AircraftID]["HexIdent"] = $SBS1Date[4];
							$DataArray[$SessionID][$AircraftID]["Country"] = $this->GetCountry($SBS1Date[4]);
							$DataArray[$SessionID][$AircraftID]["CallSign"] = "n/v";
							$DataArray[$SessionID][$AircraftID]["Altitude"] = "n/v";
							$DataArray[$SessionID][$AircraftID]["GroundSpeed"] = "n/v";
							$DataArray[$SessionID][$AircraftID]["Track"] = "n/v";
							$DataArray[$SessionID][$AircraftID]["Latitude"] = "n/v";
							$DataArray[$SessionID][$AircraftID]["Longitude"] = "n/v";
							$DataArray[$SessionID][$AircraftID]["VerticalRate"] = "n/v";
							$DataArray[$SessionID][$AircraftID]["Squawk"] = "n/v";
							$DataArray[$SessionID][$AircraftID]["Alert"] = "n/v";
							$DataArray[$SessionID][$AircraftID]["Emergency"] = "n/v";
							$DataArray[$SessionID][$AircraftID]["SPI"] = "n/v";
							$DataArray[$SessionID][$AircraftID]["IsOnGround"] = "n/v";
							$DataArray[$SessionID][$AircraftID]["Distance"] = "n/v";
							$DataArray[$SessionID][$AircraftID]["Messages"] = 0;
							$DataArray[$SessionID][$AircraftID]["Country"] = "n/v";
							break;
						case "STA":
							//$this->SendDebug("ReceiveData", "STA: ".serialize($Message), 0);
							$DataArray[$SessionID][$AircraftID]["HexIdent"] = $SBS1Date[4];
							$DataArray[$SessionID][$AircraftID]["Country"] = $this->GetCountry($SBS1Date[4]);
							$DataArray[$SessionID][$AircraftID]["Status"] = $SBS1Date[10];
							switch(trim($SBS1Date[10])) { // CallSign
								case "PL": // Position Lost
									$this->SendDebug("ReceiveData", "STA Position Lost: ".serialize($Message), 0);

									break;
								case "SL": // Signal Lost
									$this->SendDebug("ReceiveData", "STA Signal Lost: ".serialize($Message), 0);

									break;
								case "RM": // Remove
									$this->SendDebug("ReceiveData", "STA Remove: ".serialize($Message), 0);
									unset($DataArray[$SessionID][$AircraftID]);
									break;
								case "AD": // Delete
									$this->SendDebug("ReceiveData", "STA Delete: ".serialize($Message), 0);

									break;
								case "OK": // OK
									$this->SendDebug("ReceiveData", "STA OK: ".serialize($Message), 0);

									break;
								default:
									    $this->SendDebug("ReceiveData", "STA Datensatz nicht auswertbar!", 0);
							}		
							//unset($DataArray[$SessionID][$AircraftID]);
							break;
						case "CLK":
							$this->SendDebug("ReceiveData", "CLK: ".serialize($Message), 0);
							break;
						case "MSG":
							//$this->SendDebug("ReceiveData", "MSG", 0);
							$DataArray[$SessionID][$AircraftID]["TransmissionType"] = $SBS1Date[1];
							$DataArray[$SessionID][$AircraftID]["HexIdent"] = $SBS1Date[4];
							$DataArray[$SessionID][$AircraftID]["Country"] = $this->GetCountry($SBS1Date[4]);
							switch($SBS1Date[1]) { // Message type
								case "1":
									$this->SendDebug("ReceiveData", "MSG 1 - Callsign: ".$SBS1Date[10], 0);
									$DataArray[$SessionID][$AircraftID]["CallSign"] = $SBS1Date[10];
									break;
								case "2":
									$this->SendDebug("ReceiveData", "MSG 2:".serialize($Message), 0);
									$DataArray[$SessionID][$AircraftID]["Altitude"] = $SBS1Date[11];
									$DataArray[$SessionID][$AircraftID]["GroundSpeed"] = $SBS1Date[12];
									$DataArray[$SessionID][$AircraftID]["Track"] = $SBS1Date[13];
									$DataArray[$SessionID][$AircraftID]["Latitude"] = $SBS1Date[14];
									$DataArray[$SessionID][$AircraftID]["Longitude"] = $SBS1Date[15];
									$DataArray[$SessionID][$AircraftID]["Distance"] = $this->GPS_Distanz($SBS1Date[14], $SBS1Date[15], $SBS1Date[11]);
									$DataArray[$SessionID][$AircraftID]["IsOnGround"] = substr($SBS1Date[21], 0, 1);


									break;
								case "3":
									$this->SendDebug("ReceiveData", "MSG 3: ".serialize($Message), 0);
									$DataArray[$SessionID][$AircraftID]["Altitude"] = $SBS1Date[11];
									$DataArray[$SessionID][$AircraftID]["Latitude"] = $SBS1Date[14];
									$DataArray[$SessionID][$AircraftID]["Longitude"] = $SBS1Date[15];
									$DataArray[$SessionID][$AircraftID]["Distance"] = $this->GPS_Distanz($SBS1Date[14], $SBS1Date[15], $SBS1Date[11]);
									$DataArray[$SessionID][$AircraftID]["Alert"] = $SBS1Date[18];
									$DataArray[$SessionID][$AircraftID]["Emergency"] = $SBS1Date[19];
									$DataArray[$SessionID][$AircraftID]["SPI"] = $SBS1Date[20];
									$DataArray[$SessionID][$AircraftID]["IsOnGround"] = substr($SBS1Date[21], 0, 1);
									break;
								case "4":
									$this->SendDebug("ReceiveData", "MSG 4: ".serialize($Message), 0);
									$DataArray[$SessionID][$AircraftID]["GroundSpeed"] = $SBS1Date[12];
									$DataArray[$SessionID][$AircraftID]["Track"] = $SBS1Date[13];
									$DataArray[$SessionID][$AircraftID]["VerticalRate"] = $SBS1Date[16];
									break;
								case "5":
									$this->SendDebug("ReceiveData", "MSG 5: ".serialize($Message), 0);
									$DataArray[$SessionID][$AircraftID]["Altitude"] = $SBS1Date[11];
									$DataArray[$SessionID][$AircraftID]["Alert"] = $SBS1Date[18];
									$DataArray[$SessionID][$AircraftID]["SPI"] = $SBS1Date[20];
									$DataArray[$SessionID][$AircraftID]["IsOnGround"] = substr($SBS1Date[21], 0, 1);
									break;
								case "6":
									$this->SendDebug("ReceiveData", "MSG 6: ".serialize($Message), 0);
									$DataArray[$SessionID][$AircraftID]["Altitude"] = $SBS1Date[11];
									$DataArray[$SessionID][$AircraftID]["Squawk"] = $SBS1Date[17];
									$DataArray[$SessionID][$AircraftID]["Alert"] = $SBS1Date[18];
									$DataArray[$SessionID][$AircraftID]["Emergency"] = $SBS1Date[19];
									$DataArray[$SessionID][$AircraftID]["SPI"] = $SBS1Date[20];
									$DataArray[$SessionID][$AircraftID]["IsOnGround"] = substr($SBS1Date[21], 0, 1);
									break;
								case "7":
									$this->SendDebug("ReceiveData", "MSG 7: ".serialize($Message), 0);
									$DataArray[$SessionID][$AircraftID]["Altitude"] = $SBS1Date[11];
									$DataArray[$SessionID][$AircraftID]["IsOnGround"] = substr($SBS1Date[21], 0, 1);
									break;
								case "8":
									$this->SendDebug("ReceiveData", "MSG 8: ".serialize($Message), 0);
									$DataArray[$SessionID][$AircraftID]["IsOnGround"] = substr($SBS1Date[21], 0, 1);
									break;

								default:
									$this->SendDebug("ReceiveData", "Datensatz nicht auswertbar: ".serialize($Message), 0);
							}
							break;
						default:
						    $this->SendDebug("ReceiveData", "Datensatz nicht auswertbar!", 0);
					}
					If (isset($DataArray[$SessionID][$AircraftID]["Messages"])) {
						$DataArray[$SessionID][$AircraftID]["Messages"] = intval($DataArray[$SessionID][$AircraftID]["Messages"]) + 1;
					}
					else {
						$DataArray[$SessionID][$AircraftID]["Messages"] = 1;
					}
					// Daten um alte Einträge bereinigen
					$CleanDataArray = $this->ReadPropertyInteger("CleanDataArray");
					foreach ($DataArray as $SessionID => $Value) {
						foreach ($DataArray[$SessionID] as $AircraftID => $Value) {
							If ($DataArray[$SessionID][$AircraftID]["Timestamp"] < (time() - ($CleanDataArray))) {
								unset($DataArray[$SessionID][$AircraftID]);
							}
						}
					}
					$this->SetTimerInterval("CleanDataArray", 30 * 1000);
					$this->SetBuffer("Data", serialize($DataArray));
					//SetValueString($this->GetIDForIdent("DataArray"), serialize($DataArray));

					IPS_SemaphoreLeave("ReceiveData");

					$this->ShowAircrafts(serialize($DataArray));
				}
				else {
					$this->SendDebug("ReceiveData", "Datenanalyse nicht moeglich!", 0);
				}
			}
		}
	}
	    
	// Beginn der Funktionen
	
	public function CleanDataArray()
	{
		If ($this->ReadPropertyBoolean("Open") == true) {
			// Modul Array entpacken
			$CleanDataArray = $this->ReadPropertyInteger("CleanDataArray");
			$DataArray = array();
			$DataArray = unserialize($this->GetBuffer("Data"));
			// Daten um alte Einträge bereinigen
			foreach ($DataArray as $SessionID => $Value) {
				foreach ($DataArray[$SessionID] as $AircraftID => $Value) {
					If ($DataArray[$SessionID][$AircraftID]["Timestamp"] < (time() - ($CleanDataArray))) {
						unset($DataArray[$SessionID][$AircraftID]);
						$this->SendDebug("CleanDataArray", "Datenbereinigung durchgeführt", 0);
					}
					
					If ($DataArray[$SessionID][$AircraftID]["Timestamp"] > time() ) {
						unset($DataArray[$SessionID][$AircraftID]);
						$this->SendDebug("CleanDataArray", "Datenbereinigung durchgeführt", 0);
					}
					
				}
			}
			$this->SetBuffer("Data", serialize($DataArray));
			
			$this->SetTimerInterval("CleanDataArray", 30 * 1000);
			$this->ShowAircrafts(serialize($DataArray));
			//SetValueString($this->GetIDForIdent("DataArray"), serialize($DataArray));
		}
	}
	    
	private function ShowAircrafts(string $DataArray) {
		$DataArray = unserialize($DataArray);
		
		$HTML = "<table border='1'>";
            	$Bottomline = "Stand: ".date('d.m.Y H:i', time());
		$HTML .= "<caption align='bottom'>$Bottomline</caption>";
		$HTML .= "<thead>";
		$HTML .= "<tr>";
		$HTML .= "<th>ICAO</th>"; // hex
		$HTML .= "<th>Flug</th>"; // flight
		$HTML .= "<th>Transponder-<br>code</th>"; // squak
		$HTML .= "<th>Höhe ft | m</th>"; // altitude
		$HTML .= "<th>Geschwindig-<br>keit kt | km/h</th>"; // speed
		$HTML .= "<th>Distanz km</th>"; // Distanz
		$HTML .= "<th>Winkel (°)</th>"; // track
		$HTML .= "<th>Anzahl<br>Nachrichten</th>"; // messages
		$HTML .= "<th>Letzter<br>Kontakt (sek)</th>"; // seen
		$HTML .= "<th>Herkunftland</th>"; // seen
		$HTML .= "</tr>";
            	$HTML .= "</thead>";
		foreach ($DataArray as $SessionID => $Value) {
			foreach ($DataArray[$SessionID] as $AircraftID => $Value) {
				$HTML .= "<tbody>";
				If ((is_numeric($DataArray[$SessionID][$AircraftID]["Latitude"])) AND (is_numeric($DataArray[$SessionID][$AircraftID]["Longitude"]))) {
					$HTML .= "<tr bgcolor=#088A08>";
				}
				else {
					$HTML .= "<tr>";
				}
				
				// ICAO
				If (isset($DataArray[$SessionID][$AircraftID]["HexIdent"])) {
					$ICAO = strtoupper($DataArray[$SessionID][$AircraftID]["HexIdent"]);
					$HTML .= "<td>$ICAO</td>";
				}
				else {
					$HTML .= "<td>---</td>";
				}
				// Flug
				If (isset($DataArray[$SessionID][$AircraftID]["CallSign"])) {
					$CallSign = $DataArray[$SessionID][$AircraftID]["CallSign"];
					$HTML .= "<td>$CallSign</td>";
				}
				else {
					$HTML .= "<td>---</td>";
				}
				// Squak
				If (isset($DataArray[$SessionID][$AircraftID]["Squawk"])) {
					$Squawk = $DataArray[$SessionID][$AircraftID]["Squawk"];
					$HTML .= "<td>$Squawk</td>";
				}
				else {
					$HTML .= "<td>---</td>";
				}
				// Höhe
				If (isset($DataArray[$SessionID][$AircraftID]["Altitude"])) {
					$Altitude_ft = number_format(intval($DataArray[$SessionID][$AircraftID]["Altitude"]), 0, "," , "."); 
					$Altitude_m = number_format(intval($DataArray[$SessionID][$AircraftID]["Altitude"]) / 3.281, 0, "," , ".");
					$Altitude = $Altitude_ft." | ".$Altitude_m;
					
					$HTML .= "<td align='center'>$Altitude</td>";
				}
				else {
					$HTML .= "<td align='center'>--- | ---</td>";
				}
				// Geschwindigkeit
				If (isset($DataArray[$SessionID][$AircraftID]["GroundSpeed"])) {
					If (is_numeric($DataArray[$SessionID][$AircraftID]["GroundSpeed"]) == true) {
						$Speed_kn = number_format(intval($DataArray[$SessionID][$AircraftID]["GroundSpeed"]), 0, "," , "."); 
						$Speed_kmh = number_format(intval($DataArray[$SessionID][$AircraftID]["GroundSpeed"]) * 1.852, 0, "," , ".");
						$Speed = $Speed_kn." | ".$Speed_kmh;
						$HTML .= "<td align='center'>$Speed</td>";
					}
					else {
						$HTML .= "<td align='center'>--- | ---</td>";
					}
				}
				else {
					$HTML .= "<td align='center'>--- | ---</td>";
				}
				// Distanz
				If (isset($DataArray[$SessionID][$AircraftID]["Distance"])) {
					If (is_numeric($DataArray[$SessionID][$AircraftID]["Distance"]) == true) {
						$Distance = number_format($DataArray[$SessionID][$AircraftID]["Distance"], 1, "," , "."); 
						$HTML .= "<td align='right'>$Distance</td>";
					}
					else {
						$HTML .= "<td align='right'>---</td>";
					}
				}
				else {
					$HTML .= "<td align='right'>---</td>";
				}
				// Winkel
				If (isset($DataArray[$SessionID][$AircraftID]["Track"])) {
					If (is_numeric($DataArray[$SessionID][$AircraftID]["Track"]) == true) {
						$Track = number_format(intval($DataArray[$SessionID][$AircraftID]["Track"]), 0, "," , "."); 
						$HTML .= "<td align='right'>$Track</td>";
					}
					else {
						$HTML .= "<td align='right'>---</td>";
					}
				}
				else {
					$HTML .= "<td align='right'>---</td>";
				}
				// Nachrichten
				If (isset($DataArray[$SessionID][$AircraftID]["Messages"])) {
					$Messages = number_format(intval($DataArray[$SessionID][$AircraftID]["Messages"]), 0, "," , ".");
					$HTML .= "<td align='right'>$Messages</td>";
				}
				else {
					$HTML .= "<td align='right'>---</td>";
				}
				// Letzte Nachricht
				If (isset($DataArray[$SessionID][$AircraftID]["Timestamp"])) {
					$LastSeen = microtime(true) - $DataArray[$SessionID][$AircraftID]["Timestamp"];
					$LastSeen = number_format($LastSeen, 1, "," , ".");
					
					$HTML .= "<td align='right'>$LastSeen</td>";
				}
				else {
					$HTML .= "<td align='right'>---</td>";
				}
				// Land
				If (isset($DataArray[$SessionID][$AircraftID]["Country"])) {
					$Country = $DataArray[$SessionID][$AircraftID]["Country"];
					
					$HTML .= "<td align='right'>$Country</td>";
				}
				else {
					$HTML .= "<td align='right'>---</td>";
				}		
				$HTML .= "</tr>";
				$HTML .= "</tbody>";
			}
		}
            	$HTML .= "</table>";           
    
            	If (GetValueString($this->GetIDForIdent("Aircrafts")) <> $HTML) {
    			SetValueString($this->GetIDForIdent("Aircrafts"), $HTML);
		}
	}
	    
	public function DataUpdate()
	{
		If ($this->ReadPropertyBoolean("Open") == true) {
			$this->Statistics();
			//$this->Aircrafts();
		}
	}
	    
	private function Statistics()
	{
    		$IP = $this->ReadPropertyString("IP");
    		$StatisticsJSON = file_get_contents('http://'.$IP.'/dump1090/data/stats.json');
    		If ($StatisticsJSON === false) {
         		$this->SendDebug("Statistics", "Fehler beim Lesen der Datei!", 0);
    		}
    		else {
        		$Statistics = array();
        		$Statistics = json_decode($StatisticsJSON);
        		If (is_object($Statistics) == false) {
            			$this->SendDebug("Statistics", "Datei ist kein Array!", 0);
        		}
        		else {
            			$StatisticArray = array("last1min" => "1 Minute", "last5min" => "5 Minuten", "last15min" => "15 Minuten", "total" => "Insgesamt");
        			
				// HTML Tabelle erstellen
            			$HTML = "<table border='1'>";
				$Bottomline = "Stand: ".date('d.m.Y H:i', $Statistics->latest->end);
            			$HTML .= "<caption align='bottom'>$Bottomline</caption>";
				$HTML .= "<thead>";
				$HTML .= "<tr>";
				$HTML .= "<th></th>";
				$HTML .= "<th>Start</th>";
				$HTML .= "<th>Samples<br>durchgeführt</th>";
				$HTML .= "<th>Samples<br>verworfen</th>";
				$HTML .= "<th>Mode AC</th>";
				$HTML .= "<th>Mode S</th>";
				$HTML .= "<th>Schlecht</th>";
				$HTML .= "<th>Unbekannte<br>ICAO</th>";
				$HTML .= "<th>Signal (dB)</th>";
				$HTML .= "<th>Noise (dB)</th>";
				$HTML .= "<th>Peak<br>Signal (dB)</th>";
				$HTML .= "<th>Starke<br>Signale</th>";
				$HTML .= "</tr>";
				$HTML .= "</thead>";
            			foreach ($StatisticArray as $Key => $Line){
                			$HTML .= "<tbody>";
                			$HTML .= "<tr>";
					$HTML .= "<th align='left'>$Line</th>";
					$Start = date('H:i', $Statistics->$Key->start);
					$HTML .= "<td>$Start</td>";       
					$SamplesProcessed = $Statistics->$Key->local->samples_processed;
					$HTML .= "<td align='right'>$SamplesProcessed</td>";     
					$SamplesDropped = $Statistics->$Key->local->samples_dropped;
					$HTML .= "<td align='right'>$SamplesDropped</td>";
					$ModeAC = $Statistics->$Key->local->modeac;
					$HTML .= "<td align='right'>$ModeAC</td>";       
					$ModeS = $Statistics->$Key->local->modes;
					$HTML .= "<td align='right'>$ModeS</td>";
					$Bad = $Statistics->$Key->local->bad;
					$HTML .= "<td align='right'>$Bad</td>";
					$UnknownICAO = $Statistics->$Key->local->unknown_icao;
					$HTML .= "<td align='right'>$UnknownICAO</td>";
					If (isset($Statistics->$Key->local->signal)) {
						$Signal = $Statistics->$Key->local->signal;
						$HTML .= "<td align='right'>$Signal</td>";
					}
                			else {
                    				$HTML .= "<td align='right'>---</td>";
                			}
					If (isset($Statistics->$Key->local->noise)) {
						$Noise = $Statistics->$Key->local->noise;
						$HTML .= "<td align='right'>$Noise</td>";
					}
                			else {
                    				$HTML .= "<td align='right'>---</td>";
                			}
					
					If (isset($Statistics->$Key->local->peak_signal)) {
						$PeakSignal = $Statistics->$Key->local->peak_signal;
						$HTML .= "<td align='right'>$PeakSignal</td>";
					}
                			else {
                    				$HTML .= "<td align='right'>---</td>";
                			}
					$StrongSignals = $Statistics->$Key->local->strong_signals;
					$HTML .= "<td align='right'>$StrongSignals</td>";
					$HTML .= "</tr>";
			    		$HTML .= "</tbody>";

            			}
			    	
			    	$HTML .= "</table>";
            			
				If (GetValueString($this->GetIDForIdent("Statistics")) <> $HTML) {
    					SetValueString($this->GetIDForIdent("Statistics"), $HTML);
				}
        		}   
    		}
	}
	    
	private function Aircrafts()
	{
    		$IP = $this->ReadPropertyString("IP");
    		$AircraftsJSON = file_get_contents('http://'.$IP.'/dump1090/data/aircraft.json');
    		If ($AircraftsJSON === false) {
			$this->SendDebug("Aircrafts", "Fehler beim Lesen der Datei!", 0);
    		}
    		else {
        		$Aircrafts = array();
        		$Aircrafts = json_decode($AircraftsJSON);
        		If (is_object($Aircrafts) == false) {
				$this->SendDebug("Aircrafts", "Datei ist kein Array!", 0);
        		}
        		else {            
   				$HTML = "<table border='1'>";
            			$OldMessageCount = GetValueInteger($this->GetIDForIdent("Messages"));
				$MessagesPerSecond = intval($Aircrafts->messages) - $OldMessageCount;
				SetValueInteger($this->GetIDForIdent("Messages"), intval($Aircrafts->messages));
				$Bottomline = "Stand: ".date('d.m.Y H:i', $Aircrafts->now)." (Nachrichten/sek: ".$MessagesPerSecond.")";
			    	$HTML .= "<caption align='bottom'>$Bottomline</caption>";
				$HTML .= "<thead>";
			    	$HTML .= "<tr>";
			    	$HTML .= "<th>ICAO</th>"; // hex
				$HTML .= "<th>Transponder-<br>code</th>"; // squak
				$HTML .= "<th>Flug</th>"; // flight
				$HTML .= "<th>Latitude</th>"; // lat
				$HTML .= "<th>Longitude</th>"; // lon
				$HTML .= "<th>NUCp</th>"; // nucp
				$HTML .= "<th>Letztes<br>Postionsupdate</th>"; // seen_pos
				$HTML .= "<th>Höhe ft|m</th>"; // altitude
				$HTML .= "<th>Vertikale<br>Rate (f/min)</th>"; // vert_rate
				$HTML .= "<th>Winkel (°)</th>"; // track
				$HTML .= "<th>Geschwindig-<br>keit kt|km/h</th>"; // speed
				$HTML .= "<th>Kategorie</th>"; // category
				$HTML .= "<th>Anzahl<br>Nachrichten</th>"; // messages
				$HTML .= "<th>Letzter<br>Kontakt (sek)</th>"; // seen
				$HTML .= "<th>RSSI (dB)</th>";
			    	$HTML .= "</tr>";
            			$HTML .= "</thead>";
				$AircraftArray = array();
            			$AircraftArray = $Aircrafts->aircraft;
				foreach ($AircraftArray as $Value){
					//SetValueString($this->GetIDForIdent("Mausefalle"), serialize($AircraftArray));
					//SetValueString($this->GetIDForIdent("MausefalleZeit"), date('d.m H:i:s', time()));
					$HTML .= "<tbody>";
					$HTML .= "<tr>";
					// ICAO
					If (isset($Value->hex)) {
						$ICAO = strtoupper($Value->hex);
						$HTML .= "<td>$ICAO</td>";
					}
					else {
						$HTML .= "<td>---</td>";
					}
					// Squak
					If (isset($Value->squawk)) {
						$Transponder = $Value->squawk;
						$HTML .= "<td>$Transponder</td>";
					}
					else {
						$HTML .= "<td>---</td>";
					}
					If (isset($Value->flight)) {
						$Flight = $Value->flight;
						$HTML .= "<td>$Flight</td>";
					}
					else {
						$HTML .= "<td>---</td>";
					}
					If (isset($Value->lat)) {
						$Latitude = $Value->lat;
						$HTML .= "<td>$Latitude</td>";
					}
					else {
						$HTML .= "<td>---</td>";
					}
					
					If (isset($Value->lon)) {
						$Longitude = $Value->lon;
						$HTML .= "<td>$Longitude</td>";
					}
					else {
						$HTML .= "<td>---</td>";
					}
					If (isset($Value->nucp)) {
						$NUCp = $Value->nucp;
						$HTML .= "<td>$NUCp</td>";
					}
					else {
						$HTML .= "<td>---</td>";
					}
					If (isset($Value->seen_pos)) {
						$SeenPos = $Value->seen_pos;
						$HTML .= "<td>$SeenPos</td>";
					}
					else {
						$HTML .= "<td>---</td>";
					}
					// Höhe
					If (isset($Value->altitude)) {
						$Altitude = intval($Value->altitude)."|".intval(intval($Value->altitude) / 3.281);
						$HTML .= "<td>$Altitude</td>";
					}
					else {
						$HTML .= "<td>---|---</td>";
					}
					If (isset($Value->vert_rate)) {
						$VertRate = $Value->vert_rate;
						$HTML .= "<td>$VertRate</td>";
					}
					else {
						$HTML .= "<td>---</td>";
					}
					
					If (isset($Value->track)) {
						$Track = $Value->track;
						$HTML .= "<td>$Track</td>";
					}
					else {
						$HTML .= "<td>---</td>";
					}
					// Geschwindigkeit
					If (isset($Value->speed)) {
						$Speed = intval($Value->speed)."|".intval(intval($Value->speed) * 1.852);
						$HTML .= "<td>$Speed</td>";
					}
					else {
						$HTML .= "<td>---|---</td>";
					}
					If (isset($Value->category)) {
						$Category = $Value->category;
						$HTML .= "<td>$Category</td>";
					}
					else {
						$HTML .= "<td>---</td>";
					}
					
					If (isset($Value->messages)) {
						$Messages = $Value->messages;
						$HTML .= "<td>$Messages</td>";
					}
					else {
						$HTML .= "<td>---</td>";
					}
					If (isset($Value->seen)) {
						$Seen = $Value->seen;
						$HTML .= "<td>$Seen</td>";
					}
					else {
						$HTML .= "<td>---</td>";
					}
					If (isset($Value->rssi)) {
						$RSSI = $Value->rssi;
						$HTML .= "<td>$RSSI</td>";
					}
					else {
						$HTML .= "<td>---</td>";
					}				 
								
					$HTML .= "</tr>";
			    		$HTML .= "</tbody>";
				}
				
            			$HTML .= "</table>";           
    
            			If (GetValueString($this->GetIDForIdent("Aircrafts")) <> $HTML) {
    					SetValueString($this->GetIDForIdent("Aircrafts"), $HTML);
				}
        		}   
    		}
	}
	    
	// Berechnet aus zwei GPS-Koordinaten die Entfernung
	private function GPS_Distanz(float $Latitude, float $Longitude, float $Altitude)
	{
		$locationObject = json_decode($this->ReadPropertyString('Location'), true);
		$HomeLatitude = $locationObject['latitude'];
		$HomeLongitude = $locationObject['longitude']; 
		$HomeHeightOverNN = $this->ReadPropertyInteger("HeightOverNN") / 1000; // Umrechnung in km
		$Altitude = $Altitude / 3.281 / 1000; // Umrechnung von ft in km
		
		$km = 0;
		$pi80 = M_PI / 180;
		$Latitude *= $pi80;
		$Longitude *= $pi80;
		$HomeLatitude *= $pi80;
		$HomeLongitude *= $pi80;

		$r = 6372.797; // mean radius of Earth in km
		$dlat = $HomeLatitude - $Latitude;
		$dlng = $HomeLongitude - $Longitude;
		$a = sin($dlat / 2) * sin($dlat / 2) + cos($Latitude) * cos($HomeLatitude) * sin($dlng / 2) * sin($dlng / 2);
		$c = 2 * atan2(sqrt($a), sqrt(1 - $a));
		$Distence2d = $r * $c;
		
		// Um Höhe korrigieren
		$dheight = $Altitude - $HomeHeightOverNN;
		$km = sqrt(pow($Distence2d, 2) + pow($dheight, 2));
		$km = round($km, 1);
	return $km;
	}
	
	private function GetCountry(string $ICAO) {

		$ICAOArray = array(0 => array("000000", "003FFF", "(unallocated)"), 1 => array("098000", "0983FF", "Djibouti"), 2 => array("4A8000", "4AFFFF", "Sweden"), 
		3 => array("730000", "737FFF", "Iran"), 4 => array("004000", "0043FF", "Zimbabwe"), 5 => array("09A000", "09AFFF", "Gambia"), 6 => array("4B0000", "4B7FFF", "Switzerland"), 
		7 => array("738000", "73FFFF", "Israel"), 8 => array("006000", "006FFF", "Mozambique"), 9 => array("09C000", "09CFFF", "Burkina Faso"), 10 => array("4B8000", "4BFFFF", "Turkey"), 
		11 => array("740000", "747FFF", "Jordan"), 12 => array("008000", "00FFFF", "South Africa"), 13 => array("09E000", "09E3FF", "Sao Tome"), 14 => array("4C0000", "4C7FFF", "Yugoslavia"), 
		15 => array("748000", "74FFFF", "Lebanon"), 16 => array("010000", "017FFF", "Egypt"), 17 => array("0A0000", "0A7FFF", "Algeria"), 18 => array("4C8000", "4C83FF", "Cyprus"), 
		19 => array("750000", "757FFF", "Malaysia"), 20 => array("018000", "01FFFF", "Libya"), 21 => array("0A8000", "0A8FFF", "Bahamas"), 22 => array("4CA000", "4CAFFF", "Ireland"), 
		23 => array("758000", "75FFFF", "Philippines"), 24 => array("020000", "027FFF", "Morocco"), 25 => array("0AA000", "0AA3FF", "Barbados"), 26 => array("4CC000", "4CCFFF", "Iceland"), 
		27 => array("760000", "767FFF", "Pakistan"), 28 => array("028000", "02FFFF", "Tunisia"), 29 => array("0AB000", "0AB3FF", "Belize"), 30 => array("4D0000", "4D03FF", "Luxembourg"), 
		31 => array("768000", "76FFFF", "Singapore"), 32 => array("030000", "0303FF", "Botswana"), 33 => array("0AC000", "0ACFFF", "Colombia"), 34 => array("4D2000", "4D23FF", "Malta"), 
		35 => array("770000", "777FFF", "Sri Lanka"), 36 => array("032000", "032FFF", "Burundi"), 37 => array("0AE000", "0AEFFF", "Costa Rica"), 38 => array("4D4000", "4D43FF", "Monaco"), 
		39 => array("778000", "77FFFF", "Syria"), 40 => array("034000", "034FFF", "Cameroon"), 41 => array("0B0000", "0B0FFF", "Cuba"), 42 => array("500000", "5003FF", "San Marino"),			
		43 => array("780000", "7BFFFF", "China"), 44 => array("035000", "0353FF", "Comoros"), 45 => array("0B2000", "0B2FFF", "El Salvador"), 46 => array("502c00", "502fff", "Latvia"), 
		47 => array("7C0000", "7FFFFF", "Australia"), 48 => array("036000", "036FFF", "Congo"), 49 => array("0B4000", "0B4FFF", "Guatemala"), 50 => array("501000", "5013FF", "Albania"), 
		51 => array("800000", "83FFFF", "India"), 52 => array("038000", "038FFF", "Côte d Ivoire"), 53 => array("0B6000", "0B6FFF", "Guyana"), 54 => array("501C00", "501FFF", "Croatia"), 
		55 => array("840000", "87FFFF", "Japan"), 56 => array("03E000", "03EFFF", "Gabon"), 57 => array("0B8000", "0B8FFF", "Haiti"), 58 => array("502C00", "502FFF", "Latvia"), 
		59 => array("880000", "887FFF", "Thailand"), 60 => array("040000", "040FFF", "Ethiopia"), 61 => array("0BA000", "0BAFFF", "Honduras"), 62 => array("503C00", "503FFF", "Lithuania"), 
		63 => array("888000", "88FFFF", "Viet Nam"), 64 => array("042000", "042FFF", "Equatorial Guinea"), 65 => array("0BC000", "0BC3FF", "St.Vincent + Grenadines"), 
		66 => array("504C00", "504FFF", "Moldova"), 67 => array("890000", "890FFF", "Yemen"), 68 => array("044000", "044FFF", "Ghana"), 69 => array("0BE000", "0BEFFF", "Jamaica"), 
		70 => array("505C00", "505FFF", "Slovakia"), 71 => array("894000", "894FFF", "Bahrain"), 72 => array("046000", "046FFF", "Guinea"), 73 => array("0C0000", "0C0FFF", "Nicaragua"), 
		74 => array("506C00", "506FFF", "Slovenia"), 75 => array("895000", "8953FF", "Brunei"), 76 => array("048000", "0483FF", "Guinea-Bissau"), 77 => array("0C2000", "0C2FFF", "Panama"), 
		78 => array("507C00", "507FFF", "Uzbekistan"), 79 => array("896000", "896FFF", "United Arab Emirates"), 80 => array("04A000", "04A3FF", "Lesotho"), 
		81 => array("0C4000", "0C4FFF", "Dominican Republic"), 82 => array("508000", "50FFFF", "Ukraine"), 83 => array("897000", "8973FF", "Solomon Islands"), 
		84 => array("04C000", "04CFFF", "Kenya"), 85 => array("0C6000", "0C6FFF", "Trinidad and Tobago"), 86 => array("510000", "5103FF", "Belarus"), 
		87 => array("898000", "898FFF", "Papua New Guinea"), 88 => array("050000", "050FFF", "Liberia"), 89 => array("0C8000", "0C8FFF", "Suriname"), 90 => array("511000", "5113FF", "Estonia"), 
		91 => array("899000", "8993FF", "Taiwan (unofficial)"), 92 => array("054000", "054FFF", "Madagascar"), 93 => array("0CA000", "0CA3FF", "Antigua & Barbuda"), 
		94 => array("512000", "5123FF", "Macedonia"), 95 => array("8A0000", "8A7FFF", "Indonesia"), 96 => array("058000", "058FFF", "Malawi"), 97 => array("0CC000", "0CC3FF", "Grenada"), 
		98 => array("513000", "5133FF", "Bosnia & Herzegovina"), 99 => array("900000", "9FFFFF", "(reserved, NAM/PAC)"), 100 => array("05A000", "05A3FF", "Maldives"), 
		101 => array("0D0000", "0D7FFF", "Mexico"), 102 => array("514000", "5143FF", "Georgia"), 103 => array("900000", "9003FF", "Marshall Islands"), 104 => array("05C000", "05CFFF", "Mali"), 
		105 => array("0D8000", "0DFFFF", "Venezuela"), 106 => array("515000", "5153FF", "Tajikistan"), 107 => array("901000", "9013FF", "Cook Islands"), 108 => array("05E000", "05E3FF", "Mauritania"), 
		109 => array("100000", "1FFFFF", "Russia"), 110 => array("600000", "6003FF", "Armenia"), 111 => array("902000", "9023FF", "Samoa"), 112 => array("060000", "0603FF", "Mauritius"), 
		113 => array("200000", "27FFFF", "(reserved, AFI)"), 114 => array("600000", "67FFFF", "(reserved, MID)"), 115 => array("A00000", "AFFFFF", "United States"), 
		116 => array("062000", "062FFF", "Niger"), 117 => array("201000", "2013FF", "Namibia"), 118 => array("600800", "600BFF", "Azerbaijan"), 119 => array("B00000", "BFFFFF", "(reserved)"), 
		120 => array("064000", "064FFF", "Nigeria"), 121 => array("202000", "2023FF", "Eritrea"), 122 => array("601000", "6013FF", "Kyrgyzstan"), 123 => array("C00000", "C3FFFF", "Canada"), 
		124 => array("068000", "068FFF", "Uganda"), 125 => array("280000", "2FFFFF", "(reserved, SAM)"), 126 => array("601800", "601BFF", "Turkmenistan"), 
		127 => array("C80000", "C87FFF", "New Zealand"), 128 => array("06A000", "06A3FF", "Qatar"), 129 => array("300000", "33FFFF", "Italy"), 130 => array("680000", "6FFFFF", "(reserved, ASIA)"), 
		131 => array("C88000", "C88FFF", "Fiji"), 132 => array("06C000", "06CFFF", "Central African Republic"), 133 => array("340000", "37FFFF", "Spain"), 134 => array("680000", "6803FF", "Bhutan"), 
		135 => array("C8A000", "C8A3FF", "Nauru"), 136 => array("06E000", "06EFFF", "Rwanda"), 137 => array("380000", "3BFFFF", "France"), 138 => array("681000", "6813FF", "Micronesia"), 
		139 => array("C8C000", "C8C3FF", "Saint Lucia"), 140 => array("070000", "070FFF", "Senegal"), 141 => array("3C0000", "3FFFFF", "Germany"), 142 => array("682000", "6823FF", "Mongolia"), 
		143 => array("C8D000", "C8D3FF", "Tonga"), 144 => array("074000", "0743FF", "Seychelles"), 145 => array("400000", "43FFFF", "United Kingdom"), 146 => array("683000", "6833FF", "Kazakhstan"), 
		147 => array("C8E000", "C8E3FF", "Kiribati"), 148 => array("076000", "0763FF", "Sierra Leone"), 149 => array("440000", "447FFF", "Austria"), 150 => array("684000", "6843FF", "Palau"), 
		151 => array("C90000", "C903FF", "Vanuatu"), 152 => array("078000", "078FFF", "Somalia"), 153 => array("448000", "44FFFF", "Belgium"), 154 => array("700000", "700FFF", "Afghanistan"), 
		155 => array("D00000", "DFFFFF", "(reserved)"), 156 => array("07A000", "07A3FF", "Swaziland"), 157 => array("450000", "457FFF", "Bulgaria"), 158 => array("702000", "702FFF", "Bangladesh"), 
		159 => array("E00000", "E3FFFF", "Argentina"), 160 => array("07C000", "07CFFF", "Sudan"), 161 => array("458000", "45FFFF", "Denmark"), 162 => array("704000", "704FFF", "Myanmar"), 
		163 => array("E40000", "E7FFFF", "Brazil"), 164 => array("080000", "080FFF", "Tanzania"), 165 => array("460000", "467FFF", "Finland"), 166 => array("706000", "706FFF", "Kuwait"), 
		167 => array("E80000", "E80FFF", "Chile"), 168 => array("084000", "084FFF", "Chad"), 169 => array("468000", "46FFFF", "Greece"), 170 => array("708000", "708FFF", "Laos"), 
		171 => array("E84000", "E84FFF", "Ecuador"), 172 => array("088000", "088FFF", "Togo"), 173 => array("470000", "477FFF", "Hungary"), 174 => array("70A000", "70AFFF", "Nepal"), 
		175 => array("E88000", "E88FFF", "Paraguay"), 176 => array("08A000", "08AFFF", "Zambia"), 177 => array("478000", "47FFFF", "Norway"), 178 => array("70C000", "70C3FF", "Oman"), 
		179 => array("E8C000", "E8CFFF", "Peru"), 180 => array("08C000", "08CFFF", "D R Congo"), 181 => array("480000", "487FFF", "Netherlands"), 182 => array("70E000", "70EFFF", "Cambodia"), 
		183 => array("E90000", "E90FFF", "Uruguay"), 184 => array("090000", "090FFF", "Angola"), 185 => array("488000", "48FFFF", "Poland"), 186 => array("710000", "717FFF", "Saudi Arabia"), 
		187 => array("E94000", "E94FFF", "Bolivia"), 188 => array("094000", "0943FF", "Benin"), 189 => array("490000", "497FFF", "Portugal"), 190 => array("718000", "71FFFF", "Korea (South)"), 
		191 => array("EC0000", "EFFFFF", "(reserved, CAR)"), 192 => array("096000", "0963FF", "Cape Verde"), 193 => array("498000", "49FFFF", "Czech Republic"), 
		194 => array("720000", "727FFF", "Korea (North)"), 195 => array("F00000", "F07FFF", "ICAO (1)"), 196 => array("098000", "0983FF", "Djibouti"), 197 => array("4A0000", "4A7FFF", "Romania"), 
		198 => array("728000", "72FFFF", "Iraq"), 199 => array("F00000", "FFFFFF", "(reserved)"), 200 => array("F09000", "F093FF", "ICAO (2)"), 201 => array("508000", "50ffff", "Ukaina"));

		$Country = "unknown";
		foreach ($ICAOArray as $CountryArea) {
			If ((hexdec($ICAO) >= hexdec($CountryArea[0])) AND (hexdec($ICAO) <= hexdec($CountryArea[1]))) {
				$Country = $CountryArea[2];
				break;
			}
		}

	return $Country;
	}   
	    
	    
	    
	    
	private function GetParentID()
	{
		$ParentID = (IPS_GetInstance($this->InstanceID)['ConnectionID']);  
	return $ParentID;
	}   
	    
	/*
	messages: the total number of Mode S messages processed since dump1090 started.
	aircraft: an array of JSON objects, one per known aircraft. Each aircraft has the following keys. Keys will be omitted if data is not available.
	hex: the 24-bit ICAO identifier of the aircraft, as 6 hex digits. The identifier may start with '~', this means that the address is a non-ICAO address (e.g. from TIS-B).
	squawk: the 4-digit squawk (octal representation)
	flight: the flight name / callsign
	lat, lon: the aircraft position in decimal degrees
	nucp: the NUCp (navigational uncertainty category) reported for the position
	seen_pos: how long ago (in seconds before "now") the position was last updated
	altitude: the aircraft altitude in feet, or "ground" if it is reporting it is on the ground
	vert_rate: vertical rate in feet/minute
	track: true track over ground in degrees (0-359)
	speed: reported speed in kt. This is usually speed over ground, but might be IAS - you can't tell the difference here, sorry!
	messages: total number of Mode S messages received from this aircraft
	seen: how long ago (in seconds before "now") a message was last received from this aircraft
	rssi: recent average RSSI (signal power), in dbFS; this will always be negative.
	*/
}
?>
