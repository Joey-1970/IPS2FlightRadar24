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
		$this->RegisterVariableString("Aircrafts_2", "Flugzeuge", "~HTMLBox", 30);
		$this->RegisterVariableInteger("Messages", "Nachrichten", "", 40);
		
		//$this->RegisterVariableString("Mausefalle", "Mausefalle", "", 100);
		//$this->RegisterVariableString("MausefalleZeit", "MausefalleZeit", "", 110);
		$this->RegisterVariableString("DataArray", "DataArray", "~TextBox", 120);
		
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
		
		$MessageParts = explode(chr(13), $Buffer);
		
		foreach ($MessageParts as $Message) {
			$this->SendDebug("ReceiveData", serialize($Message), 0);
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
					$Timestamp = strtotime($SBS1Date[6]." ".$SBS1Date[7]);
					$DataArray[$SessionID][$AircraftID]["Timestamp"] = $Timestamp;
					$DataArray[$SessionID][$AircraftID]["DateMessageLogged"] = $SBS1Date[8];
					$DataArray[$SessionID][$AircraftID]["TimeMessageLogged"] = $SBS1Date[9];

					switch($MessageType) { // Message type
						case "SEL":
							$this->SendDebug("ReceiveData", "SEL", 0);
							$DataArray[$SessionID][$AircraftID]["HexIdent"] = $SBS1Date[4];
							$DataArray[$SessionID][$AircraftID]["CallSign"] = $SBS1Date[10];
							break;
						case "ID":
							$this->SendDebug("ReceiveData", "ID", 0);
							$DataArray[$SessionID][$AircraftID]["HexIdent"] = $SBS1Date[4];
							$DataArray[$SessionID][$AircraftID]["CallSign"] = $SBS1Date[10];
							break;
						case "AIR":
							$this->SendDebug("ReceiveData", "AIR", 0);
							$DataArray[$SessionID][$AircraftID]["TransmissionType"] = "n/v";
							$DataArray[$SessionID][$AircraftID]["HexIdent"] = $SBS1Date[4];
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
							$DataArray[$SessionID][$AircraftID]["Messages"] = "n/v";
							break;
						case "STA":
							//$this->SendDebug("ReceiveData", "STA", 0);
							$DataArray[$SessionID][$AircraftID]["HexIdent"] = $SBS1Date[4];
							$DataArray[$SessionID][$AircraftID]["Status"] = $SBS1Date[10];
							switch(trim($SBS1Date[10])) { // CallSign
								case "PL": // Position Lost
									$this->SendDebug("ReceiveData", "STA Position Lost", 0);

									break;
								case "RM": // Remove
									$this->SendDebug("ReceiveData", "STA Remove", 0);

									break;
								case "AD": // Delete
									$this->SendDebug("ReceiveData", "STA Delete", 0);

									break;
								case "OK": // OK
									$this->SendDebug("ReceiveData", "STA OK", 0);

									break;
								default:
									    throw new Exception("STA Invalid Ident");
							}		
							//unset($DataArray[$SessionID][$AircraftID]);
							break;
						case "CLK":
							$this->SendDebug("ReceiveData", "CLK", 0);
							break;
						case "MSG":
							//$this->SendDebug("ReceiveData", "MSG", 0);
							$DataArray[$SessionID][$AircraftID]["TransmissionType"] = $SBS1Date[1];
							$DataArray[$SessionID][$AircraftID]["HexIdent"] = $SBS1Date[4];
							switch($SBS1Date[1]) { // Message type
								case "1":
									$this->SendDebug("ReceiveData", "MSG 1 - Callsign: ".$SBS1Date[10], 0);
									$DataArray[$SessionID][$AircraftID]["CallSign"] = $SBS1Date[10];
									break;
								case "2":
									$this->SendDebug("ReceiveData", "MSG 2", 0);
									$DataArray[$SessionID][$AircraftID]["Altitude"] = $SBS1Date[11];
									$DataArray[$SessionID][$AircraftID]["GroundSpeed"] = $SBS1Date[12];
									$DataArray[$SessionID][$AircraftID]["Track"] = $SBS1Date[13];
									$DataArray[$SessionID][$AircraftID]["Latitude"] = $SBS1Date[14];
									$DataArray[$SessionID][$AircraftID]["Longitude"] = $SBS1Date[15];
									$DataArray[$SessionID][$AircraftID]["Distance"] = $this->GPS_Distanz($SBS1Date[14], $SBS1Date[15], $SBS1Date[11]);
									$DataArray[$SessionID][$AircraftID]["IsOnGround"] = substr($SBS1Date[21], 0, 1);


									break;
								case "3":
									$this->SendDebug("ReceiveData", "MSG 3", 0);
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
									$this->SendDebug("ReceiveData", "MSG 4", 0);
									$DataArray[$SessionID][$AircraftID]["GroundSpeed"] = $SBS1Date[12];
									$DataArray[$SessionID][$AircraftID]["Track"] = $SBS1Date[13];
									$DataArray[$SessionID][$AircraftID]["VerticalRate"] = $SBS1Date[16];
									break;
								case "5":
									$this->SendDebug("ReceiveData", "MSG 5", 0);
									$DataArray[$SessionID][$AircraftID]["Altitude"] = $SBS1Date[11];
									$DataArray[$SessionID][$AircraftID]["Alert"] = $SBS1Date[18];
									$DataArray[$SessionID][$AircraftID]["SPI"] = $SBS1Date[20];
									$DataArray[$SessionID][$AircraftID]["IsOnGround"] = substr($SBS1Date[21], 0, 1);
									break;
								case "6":
									$this->SendDebug("ReceiveData", "MSG 6", 0);
									$DataArray[$SessionID][$AircraftID]["Altitude"] = $SBS1Date[11];
									$DataArray[$SessionID][$AircraftID]["Squawk"] = $SBS1Date[17];
									$DataArray[$SessionID][$AircraftID]["Alert"] = $SBS1Date[18];
									$DataArray[$SessionID][$AircraftID]["Emergency"] = $SBS1Date[19];
									$DataArray[$SessionID][$AircraftID]["SPI"] = $SBS1Date[20];
									$DataArray[$SessionID][$AircraftID]["IsOnGround"] = substr($SBS1Date[21], 0, 1);
									break;
								case "7":
									$this->SendDebug("ReceiveData", "MSG 7", 0);
									$DataArray[$SessionID][$AircraftID]["Altitude"] = $SBS1Date[11];
									$DataArray[$SessionID][$AircraftID]["IsOnGround"] = substr($SBS1Date[21], 0, 1);
									break;
								case "8":
									$this->SendDebug("ReceiveData", "MSG 8", 0);
									$DataArray[$SessionID][$AircraftID]["IsOnGround"] = substr($SBS1Date[21], 0, 1);
									break;

								default:
								    throw new Exception("MSG Invalid Ident");
							}
							break;
						default:
						    throw new Exception("Invalid Ident");
					}
					$DataArray[$SessionID][$AircraftID]["Messages"] = intval($DataArray[$SessionID][$AircraftID]["Messages"]) + 1;
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
					SetValueString($this->GetIDForIdent("DataArray"), serialize($DataArray));

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
				}
			}
			$this->SetBuffer("Data", serialize($DataArray));
			
			$this->SetTimerInterval("CleanDataArray", 30 * 1000);
			$this->ShowAircrafts(serialize($DataArray));
			SetValueString($this->GetIDForIdent("DataArray"), serialize($DataArray));
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
		$HTML .= "<th>Höhe ft|m</th>"; // altitude
		$HTML .= "<th>Geschwindig-<br>keit kt|km/h</th>"; // speed
		$HTML .= "<th>Distanz km</th>"; // Distanz
		$HTML .= "<th>Winkel (°)</th>"; // track
		$HTML .= "<th>Anzahl<br>Nachrichten</th>"; // messages
		$HTML .= "<th>Letzter<br>Kontakt (sek)</th>"; // seen
		$HTML .= "</tr>";
            	$HTML .= "</thead>";
		foreach ($DataArray as $SessionID => $Value) {
			foreach ($DataArray[$SessionID] as $AircraftID => $Value) {
				$HTML .= "<tbody>";
				$HTML .= "<tr>";
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
					$Altitude = intval($DataArray[$SessionID][$AircraftID]["Altitude"])."|".intval(intval($DataArray[$SessionID][$AircraftID]["Altitude"]) / 3.281);
					$HTML .= "<td>$Altitude</td>";
				}
				else {
					$HTML .= "<td>---|---</td>";
				}
				// Geschwindigkeit
				If (isset($DataArray[$SessionID][$AircraftID]["GroundSpeed"])) {
					$Speed = intval($DataArray[$SessionID][$AircraftID]["GroundSpeed"])."|".intval(intval($DataArray[$SessionID][$AircraftID]["GroundSpeed"]) * 1.852);
					$HTML .= "<td>$Speed</td>";
				}
				else {
					$HTML .= "<td>---|---</td>";
				}
				// Distanz
				If (isset($DataArray[$SessionID][$AircraftID]["Distance"])) {
					$Distance = $DataArray[$SessionID][$AircraftID]["Distance"];
					$HTML .= "<td>$Distance</td>";
				}
				else {
					$HTML .= "<td>---</td>";
				}
				// Winkel
				If (isset($DataArray[$SessionID][$AircraftID]["Track"])) {
					$Track = $DataArray[$SessionID][$AircraftID]["Track"];
					$HTML .= "<td>$Track</td>";
				}
				else {
					$HTML .= "<td>---</td>";
				}
				// Nachrichten
				If (isset($DataArray[$SessionID][$AircraftID]["Messages"])) {
					$Messages = $DataArray[$SessionID][$AircraftID]["Messages"];
					$HTML .= "<td>$Messages</td>";
				}
				else {
					$HTML .= "<td>---</td>";
				}
				// Letzte Nachricht
				If (isset($DataArray[$SessionID][$AircraftID]["Timestamp"])) {
					$LastSeen = time() - $DataArray[$SessionID][$AircraftID]["Timestamp"];
					$HTML .= "<td>$LastSeen</td>";
				}
				else {
					$HTML .= "<td>---</td>";
				}
						
				$HTML .= "</tr>";
				$HTML .= "</tbody>";
			}
		}
            	$HTML .= "</table>";           
    
            	If (GetValueString($this->GetIDForIdent("Aircrafts_2")) <> $HTML) {
    			SetValueString($this->GetIDForIdent("Aircrafts_2"), $HTML);
		}
	}
	    
	public function DataUpdate()
	{
		If ($this->ReadPropertyBoolean("Open") == true) {
			$this->Statistics();
			$this->Aircrafts();
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
