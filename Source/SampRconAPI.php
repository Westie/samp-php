<?php
/**
 *	This API also connects directly to the server, but instead of using the
 *	query system, depends on the RCON system. 
 *	
 *	This system, unlike the query system (in my opinion) is not able to
 *	handle as many calls, so please use this wisely.
 *
 *	@package sampAPI
 *	@version 1.2
 *	@author David Weston <westie@typefish.co.uk>
 *	@copyright 2010; http://www.typefish.co.uk/licences/
 */


class SampRconAPI
{
	/**
	 *	@ignore
	 */
	private $rSocket = false;
	
	
	/**
	 *	@ignore
	 */
	private $aServer = array();
	
	
	/**
	 *	Creation of the RCON class.
	 *
	 */
	public function __construct($sServer, $iPort, $sPassword)
	{
		/* Fill some arrays. */
		$this->aServer[0] = $sServer;
		$this->aServer[1] = $iPort;
		$this->aServer[2] = $sPassword;
		
		/* Start the connection. */	
		$this->rSocket = fsockopen('udp://'.$this->aServer[0], $this->aServer[1], $iError, $sError, 2);
		
		if(!$this->rSocket)
		{
			$this->aServer[4] = false;
			return;
		}
		
		socket_set_timeout($this->rSocket, 2);
		
		$sPacket = 'SAMP';
		$sPacket .= chr(strtok($this->aServer[0], '.'));
		$sPacket .= chr(strtok('.'));
		$sPacket .= chr(strtok('.'));
		$sPacket .= chr(strtok('.'));
		$sPacket .= chr($this->aServer[1] & 0xFF);
		$sPacket .= chr($this->aServer[1] >> 8 & 0xFF);
		$sPacket .= 'p4150';
		
		fwrite($this->rSocket, $sPacket);
		
		if(fread($this->rSocket, 10))
		{
			if(fread($this->rSocket, 5) == 'p4150')
			{
				$this->aServer[4] = true;
				return;
			}
		}
		
		$this->aServer[4] = false;
	}
	
	
	/**
	 *	@ignore
	 */
	public function __destruct()
	{
		@fclose($this->rSocket);
	}
	
	
	/**
	 *	Used to tell if the server is ready to accept queries.
	 *
	 *	If false is returned, then it is suggested that you remove the
	 *	class from active use, so that you can reload the class if needs
	 *	be.
	 *
	 *	@return bool true if success, false if failure.
	 */
	public function isOnline()
	{
		return $this->aServer[4];
	}
	
	
	/**
	 *	Retrieves the command list.
	 *
	 *	@return array Array of available RCON commands.
	 */
	public function getCommandList()
	{
		$aCommands = $this->packetSend('cmdlist');
		unset($aCommands[0]);
		
		foreach($aCommands as &$sCommand)
		{
			$sCommand = trim($sCommand);
		}
		
		return $aCommands;
	}
	
	
	/**
	 *	Retrieves and parses the server variables.
	 *
	 *	@return array Array of current server variables.
	 */
	public function getServerVariables()
	{
		$aVariables = $this->packetSend('varlist');
		unset($aVariables[0]);
		$aReturn = array();
		
		foreach($aVariables as $sString)
		{
			preg_match('/(.*)=[\s]+(.*)/', $sString, $aMatches);
			
			if($aMatches[2][0] == '"')
			{
				preg_match('/\"(.*)\"[\s]+\(/', $aMatches[2], $aTemp);
				$aReturn[trim($aMatches[1])] = $aTemp[1];
			}
			else
			{
				preg_match('/(.*?)\s+\(/', $aMatches[2], $aTemp);
				$aReturn[trim($aMatches[1])] = $aTemp[1];
			}
		}
		
		return $aReturn;
	}
	
	
	/**
	 *	Sets the server's weather to the one specified.
	 *
	 *	@param integer $iWeatherID Weather ID
	 */
	public function setWeather($iWeatherID)
	{
		$this->packetSend('weather '.$iWeatherID, false);
	}
	
	
	/**
	 *	Sets the server's gravity to the one specified.
	 *
	 *	@param float $fGravity Gravity amount (0.008 is default)
	 */
	public function setGravity($fGravity)
	{
		$this->packetSend('gravity '.$fGravity, false);
	}
	
	
	/**
	 *	Bans a player ID from the server.
	 *
	 *	@param integer $iPlayerID Player ID
	 *	@return array Output from ban.
	 */
	public function playerBan($iPlayerID)
	{
		return $this->packetSend('ban '.$iPlayerID);
	}
	
	
	/**
	 *	Kicks a player ID from the server.
	 *
	 *	@param integer $iPlayerID Player ID
	 *	@return array Output from kick.
	 */
	public function playerKick($iPlayerID)
	{
		return $this->packetSend('kick '.$iPlayerID);
	}
	
	
	/**
	 *	Bans an IP address from the server.
	 *
	 *	@param string $sIPAddress IP Address
	 *	@return array Output from ban.
	 */
	public function addressBan($sIPAddress)
	{
		return $this->packetSend('banip '.$sIPAddress);
	}
	
	
	/**
	 *	Unbans an IP address from the server.
	 *
	 *	@param string $sIPAddress IP Address
	 */
	public function addressUnban($sIPAddress)
	{
		return $this->packetSend('unbanip '.$sIPAddress);
	}
	
	
	/**
	 *	Reloads the log on a server - useful when the log doesn't exist
	 *	any more.
	 */
	public function reloadLogs()
	{
		return $this->packetSend('reloadlog');
	}
	
	
	/**
	 *	Reloads the ban file on a server.
	 *
	 */
	public function reloadBans()
	{
		return $this->packetSend('reloadbans');
	}
	
	
	/**
	 *	Send a message as an admin to the players of the server.
	 *
	 *	@param string $sMessage Message
	 */
	public function adminSay($sMessage)
	{
		$this->packetSend('say '.$sMessage, false);
	}
	
	
	/**
	 *	Change the gamemode in the server.
	 *
	 *	@param string $sGamemode Gamemode
	 */
	public function gameChangeMode($sGamemode)
	{
		$this->packetSend('changemode '.$sGamemode, false);
	}
	
	
	/**
	 *	Sends a call to GMX.
	 */
	public function gameNextMode()
	{
		$this->packetSend('gmx', false);
	}
	
	
	/**
	 *	Executes a file that contains server configuration.
	 *
	 *	@param string $sConfig Server config name/location.
	 */
	public function gameExec($sConfig)
	{
		return $this->packetSend('exec '.$sConfig);
	}
	
	
	/**
	 *	Loads a filterscript.
	 *
	 *	@param string $sFilterscript Filterscript name/location.
	 */
	public function gameLoadFilterscript($sFilterscript)
	{
		return $this->packetSend('loadfs '.$sFilterscript);
	}
	
	
	/**
	 *	Unloads a filterscript.
	 *
	 *	@param string $sFilterscript Filterscript name/location.
	 */
	public function gameUnloadFilterscript($sFilterscript)
	{
		return $this->packetSend('unloadfs '.$sFilterscript);
	}
	
	
	/**
	 *	Reloads a filterscript.
	 *
	 *	@param string $sFilterscript Filterscript name/location.
	 */
	public function gameReloadFilterscript($sFilterscript)
	{
		return $this->packetSend('reloadfs '.$sFilterscript);
	}
	
	
	/**
	 *	Shuts down the server, without any verification.
	 */
	public function gameExit()
	{
		$this->packetSend('exit', false);
	}
	
	
	/**
	 *	Send an RCON command.
	 *
	 *	@param string $sCommand Command to send to the server.
	 *	@param float $fDelay Seconds to capture data, or false to retrieve no data.
	 *	@return array Array of output, in order of receipt.
	 */
	public function Call($sCommand, $fDelay = 1.0)
	{
		return $this->packetSend($sCommand, $fDelay);
	}
	
	
	/**
	 *	Send an RCON command.
	 *
	 *	@ignore
	 *	@see SampRconApi::Call()
	 */
	public function packetSend($sCommand, $fDelay = 1.0)
	{
		fwrite($this->rSocket, $this->packetCreate($sCommand));
		
		if($fDelay === false)
		{
			return;
		}
		
		$aReturn = array();
		$iMicrotime = microtime(true) + $fDelay;
		
		while(microtime(true) < $iMicrotime)
		{
			$sTemp = substr(fread($this->rSocket, 128), 13);
			
			if(strlen($sTemp))
			{
				$aReturn[] = $sTemp;
			}
			else
			{
				break;
			}
		}
		
		return $aReturn;
	}
	
	
	/**
	 *	@ignore
	 */
	private function packetCreate($sCommand)
	{
		$sPacket = 'SAMP';
		$sPacket .= chr(strtok($this->aServer[0], '.'));
		$sPacket .= chr(strtok('.'));
		$sPacket .= chr(strtok('.'));
		$sPacket .= chr(strtok('.'));
		$sPacket .= chr($this->aServer[1] & 0xFF);
		$sPacket .= chr($this->aServer[1] >> 8 & 0xFF);
		$sPacket .= 'x';
		
		$sPacket .= chr(strlen($this->aServer[2]) & 0xFF);
		$sPacket .= chr(strlen($this->aServer[2]) >> 8 & 0xFF);
		$sPacket .= $this->aServer[2];
		$sPacket .= chr(strlen($sCommand) & 0xFF);
		$sPacket .= chr(strlen($sCommand) >> 8 & 0xFF);
		$sPacket .= $sCommand;
		
		return $sPacket;
	}
}