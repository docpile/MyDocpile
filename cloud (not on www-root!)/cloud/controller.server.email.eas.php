<?php
/**
 * ============================================================================
 * MODULE: Webmail EAS Backend Controller 
 * ============================================================================
 * Manages live EAS connections
 */
 

if (php_sapi_name() !== 'cli' && basename($_SERVER['PHP_SELF']) == basename(__FILE__)) {
    die('Direct access not permitted');
}


/**
 * ============================================================================
 * NATIVE WBXML PARSER (WAP Binary XML)
 * Zero-dependency decoder and encoder for Exchange ActiveSync binary payloads.
 * ============================================================================
 */
class MyCloudWBXML {
    private $stream;
    private $pos = 0;
    private $currentPage = 0;
    private $stringTable = [];

    // Complete MS-ASCMD 16.1 Specification Code Pages
    private $codePages = [
        0 => [ 5=>'Sync', 6=>'Responses', 7=>'Add', 8=>'Change', 9=>'Delete', 10=>'Fetch', 11=>'SyncKey', 12=>'ClientId', 13=>'ServerId', 14=>'Status', 15=>'Collection', 16=>'Class', 17=>'Version', 18=>'CollectionId', 19=>'GetChanges', 20=>'MoreAvailable', 21=>'WindowSize', 22=>'Commands', 23=>'Options', 24=>'FilterType', 25=>'Truncation', 26=>'RTFTruncation', 27=>'Conflict', 28=>'Collections', 29=>'ApplicationData', 30=>'DeletesAsMoves', 31=>'NotifyGUID', 32=>'Supported', 33=>'SoftDelete', 34=>'MIMESupport', 35=>'MIMETruncation', 36=>'Wait', 37=>'Limit', 38=>'Partial' ],
        1 => [ 5=>'Anniversary', 6=>'AssistantName', 7=>'AssistantPhoneNumber', 8=>'Birthday', 9=>'Body', 10=>'BodySize', 11=>'BodyTruncated', 12=>'Business2PhoneNumber', 13=>'BusinessAddressCity', 14=>'BusinessAddressCountry', 15=>'BusinessAddressPostalCode', 16=>'BusinessAddressState', 17=>'BusinessAddressStreet', 18=>'BusinessFaxNumber', 19=>'BusinessPhoneNumber', 20=>'CarPhoneNumber', 21=>'Categories', 22=>'Category', 23=>'Children', 24=>'Child', 25=>'CompanyName', 26=>'Department', 27=>'Email1Address', 28=>'Email2Address', 29=>'Email3Address', 30=>'FileAs', 31=>'FirstName', 32=>'Home2PhoneNumber', 33=>'HomeAddressCity', 34=>'HomeAddressCountry', 35=>'HomeAddressPostalCode', 36=>'HomeAddressState', 37=>'HomeAddressStreet', 38=>'HomeFaxNumber', 39=>'HomePhoneNumber', 40=>'JobTitle', 41=>'LastName', 42=>'MiddleName', 43=>'MobilePhoneNumber', 44=>'Nickname', 45=>'OfficeLocation', 46=>'OtherAddressCity', 47=>'OtherAddressCountry', 48=>'OtherAddressPostalCode', 49=>'OtherAddressState', 50=>'OtherAddressStreet', 51=>'PagerNumber', 52=>'RadioPhoneNumber', 53=>'Spouse', 54=>'Suffix', 55=>'Title', 56=>'WebPage', 57=>'YomiCompanyName', 58=>'YomiFirstName', 59=>'YomiLastName', 68=>'Picture' ],
        2 => [ 15=>'DateReceived', 16=>'DisplayName', 17=>'DisplayTo', 18=>'Importance', 19=>'MessageClass', 20=>'Subject', 21=>'Read', 22=>'To', 23=>'Cc', 24=>'From', 25=>'ReplyTo', 26=>'AllDayEvent', 27=>'Categories', 28=>'Category', 29=>'DtStamp', 30=>'EndTime', 31=>'InstanceType', 32=>'BusyStatus', 33=>'Location', 34=>'MeetingRequest', 35=>'Organizer', 36=>'RecurrenceId', 37=>'Reminder', 38=>'ResponseRequested', 39=>'Recurrences', 40=>'Recurrence', 41=>'Type', 42=>'Until', 43=>'Occurrences', 44=>'Interval', 45=>'DayOfWeek', 46=>'DayOfMonth', 47=>'WeekOfMonth', 48=>'MonthOfYear', 49=>'StartTime', 50=>'Sensitivity', 51=>'TimeZone', 52=>'GlobalObjId', 53=>'ThreadTopic', 54=>'MIMEData', 55=>'MIMETruncated', 56=>'MIMESize', 57=>'InternetCPID', 58=>'Flag', 59=>'Status', 60=>'ContentClass', 61=>'FlagType', 62=>'CompleteTime', 63=>'DisallowNewTimeProposal' ],
        4 => [ 5=>'CalId', 6=>'OrganizerName', 7=>'OrganizerEmail', 8=>'Location', 9=>'EndTime', 10=>'RecurrenceId', 11=>'StartTime', 12=>'Sensitivity', 13=>'BusyStatus', 14=>'AllDayEvent', 15=>'Reminder', 16=>'RTF', 17=>'DtStamp', 18=>'EndTimeUnspecified', 19=>'Privilege', 20=>'MeetingStatus', 21=>'Attendees', 22=>'Attendee', 23=>'AttendeeEmail', 24=>'AttendeeName', 26=>'AttendeeType', 27=>'Categories', 28=>'Category', 33=>'Subject', 34=>'UID', 36=>'Recurrence', 37=>'Type', 38=>'Until', 39=>'Occurrences', 40=>'Interval', 41=>'DayOfWeek', 42=>'DayOfMonth', 43=>'WeekOfMonth', 44=>'MonthOfYear', 50=>'GlobalObjId' ],
        5 => [ 5=>'MoveItems', 6=>'Move', 7=>'SrcMsgId', 8=>'SrcFldId', 9=>'DstFldId', 10=>'Response', 11=>'Status', 12=>'DstMsgId' ],
        7 => [ 5=>'Folders', 6=>'Folder', 7=>'DisplayName', 8=>'ServerId', 9=>'ParentId', 10=>'Type', 11=>'Response', 12=>'Status', 13=>'ContentClass', 14=>'Changes', 15=>'Add', 16=>'Delete', 17=>'Update', 18=>'SyncKey', 19=>'FolderCreate', 20=>'FolderDelete', 21=>'FolderUpdate', 22=>'FolderSync', 23=>'Count', 24=>'Version' ],
        14=> [ 5=>'Provision', 6=>'Policies', 7=>'Policy', 8=>'PolicyType', 9=>'PolicyKey', 10=>'Data', 11=>'Status', 12=>'RemoteWipe', 13=>'EASProvisionDoc', 14=>'DevicePasswordEnabled', 15=>'AlphanumericDevicePasswordRequired', 16=>'RequireStorageCardEncryption', 17=>'PasswordRecoveryEnabled', 18=>'DocumentBrowseEnabled', 19=>'AttachmentsEnabled', 20=>'MinDevicePasswordLength', 21=>'MaxInactivityTimeDeviceLock', 22=>'MaxDevicePasswordFailedAttempts', 23=>'MaxAttachmentSize', 24=>'AllowSimpleDevicePassword', 25=>'DevicePasswordExpiration', 26=>'DevicePasswordHistory' ],
        17=> [ 5=>'BodyPreference', 6=>'Type', 7=>'TruncationSize', 8=>'AllOrNone', 10=>'Body', 11=>'Data', 12=>'EstimatedDataSize', 13=>'Truncated', 14=>'Attachments', 15=>'Attachment', 16=>'DisplayName', 17=>'FileReference', 18=>'Method', 19=>'ContentId', 20=>'ContentLocation', 21=>'IsInline', 22=>'NativeBodyType', 23=>'ContentType', 24=>'Preview', 25=>'BodyPartPreference', 26=>'BodyPart', 27=>'Status' ],
        18=> [ 5=>'Settings', 6=>'Status', 7=>'Get', 8=>'Set', 9=>'Oof', 10=>'OofState', 11=>'StartTime', 12=>'EndTime', 13=>'OofMessage', 14=>'AppliesToInternal', 15=>'AppliesToExternalKnown', 16=>'AppliesToExternalUnknown', 17=>'Enabled', 18=>'ReplyMessage', 19=>'BodyType', 20=>'DevicePassword', 21=>'Password', 22=>'DeviceInformation', 23=>'Model', 24=>'IMEI', 25=>'FriendlyName', 26=>'OS', 27=>'OSLanguage', 28=>'PhoneNumber', 29=>'UserInformation', 30=>'EmailAddresses', 31=>'SmtpAddress', 32=>'UserAgent', 33=>'EnableOutboundSMS', 34=>'MobileOperator', 35=>'PrimarySmtpAddress', 36=>'Accounts', 37=>'Account', 38=>'AccountId', 39=>'AccountName', 40=>'UserDisplayName', 41=>'SendDisabled', 43=>'RightsManagementInformation' ],
		20=> [ 5=>'ItemOperations', 6=>'Fetch', 7=>'Store', 8=>'Options', 9=>'Range', 10=>'Total', 11=>'Properties', 12=>'Data', 13=>'Status', 14=>'Response', 15=>'Version', 16=>'Schema', 17=>'Part', 18=>'EmptyFolderContents', 19=>'DeleteSubFolders', 20=>'UserName', 21=>'Password', 22=>'Move', 23=>'DstFldId', 24=>'DstMsgId', 25=>'FolderId' ],
        21=> [ 5=>'SendMail', 6=>'SmartForward', 7=>'SmartReply', 8=>'SaveInSentItems', 9=>'ReplaceMime', 11=>'Source', 12=>'FolderId', 13=>'ItemId', 14=>'LongId', 15=>'InstanceId', 16=>'Mime', 17=>'ClientId', 18=>'Status', 19=>'AccountId' ]
    ];

    public function decode($binaryData) {
        $this->stream = $binaryData;
        $this->pos = 0;
        
        $version = $this->readByte();
        $publicId = $this->readInt();
        $charset = $this->readInt();
        $strTableLength = $this->readInt();
        
        if ($strTableLength > 0) {
            $this->stringTable = substr($this->stream, $this->pos, $strTableLength);
            $this->pos += $strTableLength;
        }

        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->substituteEntities = false;
        $dom->resolveExternals = false;
        $this->parseNode($dom, $dom);
        return $dom;
    }

    private function parseNode($dom, $parent) {
        while ($this->pos < strlen($this->stream)) {
            $token = $this->readByte();
            
            switch ($token) {
                case 0x00: // Switch Page
                    $this->currentPage = $this->readByte();
                    break;
                case 0x01: // End
                    return;
                case 0x03: // Inline String
                    $parent->appendChild($dom->createTextNode($this->readString()));
                    break;
                case 0x04: // Opaque Data
                case 0xC3: 
                    $length = $this->readInt();
                    if ($length < 0 || $this->pos + $length > strlen($this->stream)) {
                        throw new Exception("WBXML memory boundary violation.");
                    }
                    $data = substr($this->stream, $this->pos, $length);
                    $this->pos += $length;
                    $parent->appendChild($dom->createTextNode($data));
                    break;
                default:
                    // CORRECTED BITMASKS
                    $hasAttributes = ($token & 0x80) > 0;
                    $hasContent = ($token & 0x40) > 0;
                    $tagId = $token & 0x3F;

                    $tagName = $this->codePages[$this->currentPage][$tagId] ?? "Tag_{$this->currentPage}_{$tagId}";
                    $node = $dom->createElement($tagName);
                    $parent->appendChild($node);

                    if ($hasAttributes) {
                        while (($this->readByte()) !== 0x01) { } // Skip attributes (rare in EAS)
                    }

                    if ($hasContent) {
                        $this->parseNode($dom, $node);
                    }
                    break;
            }
        }
    }

    private function readByte() { 
        if ($this->pos >= strlen($this->stream)) return 0x01;
        return ord($this->stream[$this->pos++]); 
    }
    
    private function readInt() {
        $result = 0;
        $count = 0;
        do {
            if (++$count > 5) throw new Exception("Malformed WBXML: Integer too large.");
            $byte = $this->readByte();
            $result = ($result << 7) | ($byte & 0x7F);
        } while ($byte & 0x80);
        return $result;
    }

    private function readString() {
        $start = $this->pos;
        while ($this->pos < strlen($this->stream) && $this->stream[$this->pos] !== "\x00") $this->pos++;
        $str = substr($this->stream, $start, $this->pos - $start);
        $this->pos++;
        return $str;
    }

    // Natively compiles binary WBXML out of an XML string
    public function encode($xmlString) {
        $nsToPage = [
            'AirSync' => 0, 'Contacts' => 1, 'Email' => 2, 'Calendar' => 4,
            'Move' => 5, 'FolderHierarchy' => 7, 'Provision' => 14,
            'AirSyncBase' => 17, 'Settings' => 18, 'ItemOperations' => 20, 'ComposeMail' => 21
        ];

        // Creates a case-insensitive reverse dictionary to avoid DOMDocument casing crashes
        $revMap = [];
        foreach ($this->codePages as $page => $tags) {
            foreach ($tags as $id => $name) $revMap[$page][strtolower($name)] = $id;
        }

        $dom = new DOMDocument();
        @$dom->loadXML($xmlString);

        // EAS WBXML Header: Version 1.3, Unknown Public ID, UTF-8 Charset, 0 String Table
        $this->stream = chr(0x03) . chr(0x01) . chr(0x6A) . chr(0x00);
        $this->currentPage = 0;
        
        if ($dom->documentElement) {
            $this->encodeNode($dom->documentElement, 0, $nsToPage, $revMap);
        }
        return $this->stream;
    }

    private function encodeNode($node, $defaultPage, $nsToPage, $revMap) {
        if ($node->nodeType === XML_TEXT_NODE) {
            $text = $node->nodeValue;
            if (trim($text) !== '' || $text === '0') {
                $this->stream .= chr(0x03) . $text . chr(0x00);
            }
            return;
        }
        if ($node->nodeType !== XML_ELEMENT_NODE) return;

        // Force lowercase to ensure strict matching regardless of how the XML string was typed
        $tagName = strtolower($node->localName);
        $targetPage = $defaultPage;

        // Contextually resolve MS-ASWBXML Code Pages
        if ($node->namespaceURI && isset($nsToPage[$node->namespaceURI])) {
            $targetPage = $nsToPage[$node->namespaceURI];
        } else {
            if (!isset($revMap[$targetPage][$tagName])) {
                foreach ($revMap as $p => $tags) {
                    if (isset($tags[$tagName])) {
                        $targetPage = $p;
                        break;
                    }
                }
            }
        }

        $tagId = $revMap[$targetPage][$tagName] ?? null;
        if ($tagId === null) throw new Exception("WBXML Encode Error: Unknown tag '{$node->localName}'");

        if ($targetPage !== $this->currentPage) {
            $this->stream .= chr(0x00) . chr($targetPage); // Switch Page Token
            $this->currentPage = $targetPage;
        }

        $hasContent = false;
        foreach ($node->childNodes as $child) {
            if ($child->nodeType === XML_ELEMENT_NODE || ($child->nodeType === XML_TEXT_NODE && (trim($child->nodeValue) !== '' || $child->nodeValue === '0'))) {
                $hasContent = true; break;
            }
        }

        // CORRECTED BITMASK: Write Start Tag (with Content Flag 0x40)
        $this->stream .= chr($tagId | ($hasContent ? 0x40 : 0x00));

        if ($hasContent) {
            $prevPage = $this->currentPage;
            foreach ($node->childNodes as $child) {
                $this->encodeNode($child, $targetPage, $nsToPage, $revMap);
            }
            if ($this->currentPage !== $prevPage) {
                $this->stream .= chr(0x00) . chr($prevPage);
                $this->currentPage = $prevPage;
            }
            $this->stream .= chr(0x01); // Write End Token
        }
    }
}


/**
 * ============================================================================
 * FULL EAS CONNECTOR (Exchange ActiveSync)
 * Handles Device Provisioning, Hierarchy Sync, and Email fetching natively.
 * ============================================================================
 */
class MyCloudEASClient {
    private $host;
    private $user;
    private $pass;
    private $deviceId;
    private $policyKey = '0';
    private $wbxml;
	private $stateFile;
	private $acc;
	private $oauth_token;

    public function __construct($acc, $password, $oauth_token = '') {
		$this->acc = $acc;
        $this->host = rtrim($acc['eas_host'] ?? '', '/');
        $this->user = $acc['login_user'] ?: $acc['email'];
        $this->pass = $password;
        $this->oauth_token = $oauth_token;
		$this->deviceId = strtoupper(md5('mycloud_eas_v3_' . $this->user . '_' . $this->host));
        $this->wbxml = new MyCloudWBXML();

         global $cloud_user_profiles;
         $safe_user = str_replace(['/', '\\'], '_', preg_replace('/[^a-zA-Z0-9!#$%&\'*+\-=?^_`{|}~.@]/', '', $this->user));
         
         if (!empty($cloud_user_profiles)) {
             $cache_dir = rtrim($cloud_user_profiles, '/\\') . '/' . $safe_user . '_mailcache';
         } else {
             $baseTemp = $GLOBALS['temp_dir'] ?? sys_get_temp_dir();
             $cache_dir = rtrim($baseTemp, '/\\') . '/' . $safe_user . '_tmp/' . $safe_user . '_mailcache';
         }
         
         if (!is_dir($cache_dir)) @mkdir($cache_dir, 0770, true);
         $this->stateFile = $cache_dir . '/eas_state_' . $this->deviceId . '.json';

        if (file_exists($this->stateFile)) {
            $state = json_decode(file_get_contents($this->stateFile), true);
            if ($state) $this->policyKey = $state['policyKey'] ?? '0';
        }
    }
	
    private function saveState($key, $value) {
        $state = file_exists($this->stateFile) ? json_decode(file_get_contents($this->stateFile), true) : [];
        $state[$key] = $value;
        @file_put_contents($this->stateFile, json_encode($state));
    }

    // Helper to safely pull XML nodes bypassing strict namespace rules
    private function getNodes($domOrNode, $tagName) {
        $result = [];
        if (!$domOrNode) return $result;
        $elements = $domOrNode->getElementsByTagName('*');
        foreach ($elements as $el) {
            if (strcasecmp($el->localName, $tagName) === 0 || strcasecmp($el->nodeName, $tagName) === 0) {
                $result[] = $el;
            }
        }
        return $result;
    }

    private function getNodeValue($domOrNode, $tagName, $default = '') {
        $nodes = $this->getNodes($domOrNode, $tagName);
        return count($nodes) > 0 ? $nodes[0]->nodeValue : $default;
    }

    private function request($command, $xmlPayload = null, $isBinary = false) {
        $parsedHost = parse_url($this->host, PHP_URL_HOST) ?: $this->host;
        $ip = gethostbyname($parsedHost);
        if (strpos($ip, '169.254.') === 0 || $ip === '0.0.0.0') {
            throw new Exception("EAS Connection to metadata or zero IP spaces is forbidden.");
        }

        $url = $this->host . "/Microsoft-Server-ActiveSync?Cmd={$command}&User=" . urlencode($this->user) . "&DeviceId={$this->deviceId}&DeviceType=WindowsOutlook15";
        @session_write_close(); // Prevent UI hangs on slow requests
       
        $ch = curl_init($url);
		curl_setopt($ch, CURLOPT_RESOLVE, [$parsedHost . ":443:" . $ip, $parsedHost . ":80:" . $ip]);
		curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
		curl_setopt($ch, CURLOPT_USERAGENT, 'Outlook/15.0 (Windows NT 10.0; Win64; x64)');
        $headers = [
            'MS-ASProtocolVersion: 14.0', // 16.1  for Office365/Modern Exchange
            'X-MS-PolicyKey: ' . $this->policyKey,
            'Accept:', // Suppresses cURL's default Accept header to match Outlook exactly
            'Expect:'  // Suppresses cURL's automatic 100-Continue header for large payloads
        ];

        if ($xmlPayload) {
            $headers[] = 'Content-Type: ' . ($isBinary ? 'application/vnd.ms-sync.wbxml' : 'text/xml');
            curl_setopt($ch, CURLOPT_POSTFIELDS, $xmlPayload);
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        if (($this->acc['auth_type'] ?? 'basic') === 'oauth2' && !empty($this->oauth_token)) {
            $headers[] = 'Authorization: Bearer ' . $this->oauth_token;
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        } else {
            curl_setopt($ch, CURLOPT_USERPWD, $this->user . ':' . $this->pass);
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);


        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $curlError = curl_error($ch);
        curl_close($ch);

         global $cloud_beta, $work_dir;
         if (!empty($cloud_beta)) {
             $logPath = $work_dir . 'data/_eas_debug_log.txt';
             $logData = "===== EAS REQUEST [" . date('Y-m-d H:i:s') . "] =====\n";
             $logData .= "COMMAND: {$command}\nURL: {$url}\nHTTP CODE: {$httpCode}\n";
             if ($xmlPayload) $logData .= "--- REQUEST PAYLOAD ---\n{$xmlPayload}\n";
             $logData .= "--- RESPONSE HEADERS ---\n" . trim(substr($response, 0, $headerSize)) . "\n\n";
             @file_put_contents($logPath, $logData, FILE_APPEND);
         }

        if ($httpCode === 503) {
            $retry = 0;
            if (preg_match('/Retry-After:\s*([0-9.]+)/i', substr($response, 0, $headerSize), $m)) {
                $retry = ceil((float)$m[1]);
            }
            throw new Exception("EAS Throttling (503 Service Unavailable). Exchange blocked the connection. Backoff required: {$retry} seconds.");
        }

        if ($curlError) throw new Exception("cURL Error: {$curlError}");

        $respBody = substr($response, $headerSize);

        if ($httpCode === 449) {
            if ($this->provisionDevice()) {
                return $this->request($command, $xmlPayload, $isBinary);
            }
            throw new Exception("EAS Provisioning failed. Server requires strict mobile policies.");
        }
        
        if ($httpCode === 401 || $httpCode === 403) throw new Exception("EAS Authentication failed. Check credentials or use an App Password.");
        if ($httpCode >= 400) throw new Exception("EAS HTTP Error {$httpCode}.");
        if (empty($respBody)) return null;

        // Decode the binary WBXML stream into a PHP DOMDocument natively
        try {
             $dom = $this->wbxml->decode($respBody);
             
             global $cloud_beta;
             if (!empty($cloud_beta)) {
                 $logPath = $work_dir . 'data/_eas_debug_log.txt';
                 @file_put_contents($logPath, "--- PARSED XML RESPONSE ---\n" . $dom->saveXML() . "\n\n", FILE_APPEND);
             }
             
             // Check for EAS Protocol Error Status (142 = Provisioning Required, 144 = Policy Refresh)
             $status = $this->getNodeValue($dom, 'Status');
             if (($status === '142' || $status === '144') && $command !== 'Provision') {
                 if ($this->provisionDevice()) {
                     // Re-execute the blocked request with the newly negotiated PolicyKey
                     return $this->request($command, $xmlPayload, $isBinary);
                 }
                 throw new Exception("EAS Error: Strict server policy provisioning failed (Status {$status}).");
             }
             
             return $dom;
        } catch (\Exception $e) {
            throw new Exception("Failed to decode native WBXML: " . $e->getMessage());
        }
    }

    private function provisionDevice() {
        $desktopName = 'DESKTOP-' . strtoupper(substr(md5($this->user), 0, 7));
        $reqXml = '<?xml version="1.0" encoding="utf-8"?><Provision xmlns="Provision" xmlns:settings="Settings"><settings:DeviceInformation><settings:Set><settings:Model>WindowsOutlook15</settings:Model><settings:FriendlyName>'.$desktopName.'</settings:FriendlyName><settings:OS>Windows</settings:OS><settings:OSLanguage>English</settings:OSLanguage><settings:UserAgent>Outlook/15.0</settings:UserAgent></settings:Set></settings:DeviceInformation><Policies><Policy><PolicyType>MS-EAS-Provisioning-WBXML</PolicyType></Policy></Policies></Provision>';
        $dom = $this->request('Provision', $reqXml);
        
        $tempKey = $this->getNodeValue($dom, 'PolicyKey');
        if (!$tempKey) return false;

        $ackXml = '<?xml version="1.0" encoding="utf-8"?><Provision xmlns="Provision"><Policies><Policy><PolicyType>MS-EAS-Provisioning-WBXML</PolicyType><PolicyKey>'.$tempKey.'</PolicyKey><Status>1</Status></Policy></Policies></Provision>';
        $domAck = $this->request('Provision', $ackXml);
        
        $this->policyKey = $this->getNodeValue($domAck, 'PolicyKey');
        if (!empty($this->policyKey)) {
            $this->saveState('policyKey', $this->policyKey);
        }
        return !empty($this->policyKey);
    }

    private function getSyncKey($folderId) {
        $state = file_exists($this->stateFile) ? json_decode(file_get_contents($this->stateFile), true) : [];
        $keys = $state['itemSyncKeys'] ?? [];
        return $keys[$folderId] ?? '0';
    }

    private function updateSyncKeyAndCache($folderId, $dom, $callback = null) {
        if (!$dom) return;
        $newKey = $this->getNodeValue($dom, 'SyncKey');
        if ($newKey) {
            $state = file_exists($this->stateFile) ? json_decode(file_get_contents($this->stateFile), true) : [];
            $keys = $state['itemSyncKeys'] ?? [];
            $keys[$folderId] = $newKey;
            $this->saveState('itemSyncKeys', $keys);
            
            if (is_callable($callback)) {
                $folderEmails = $state['emails_' . $folderId] ?? [];
                $folderEmails = $callback($folderEmails);
                if (is_array($folderEmails)) $this->saveState('emails_' . $folderId, $folderEmails);
            }
        }
    }

    public function createFolder($name, $parentId = '0', $type = 12) { // 12 = Mail
        $xml = '<?xml version="1.0" encoding="utf-8"?><FolderCreate xmlns="FolderHierarchy"><SyncKey>'.$this->getFolderSyncKey().'</SyncKey><ParentId>'.$parentId.'</ParentId><DisplayName>'.htmlspecialchars($name).'</DisplayName><Type>'.$type.'</Type></FolderCreate>';
        return $this->request('FolderCreate', $xml);
    }

    private function getFolderSyncKey() {
        $state = file_exists($this->stateFile) ? json_decode(file_get_contents($this->stateFile), true) : [];
        return $state['folderSyncKey'] ?? '0';
    }

    public function getFolders() {
        $state = file_exists($this->stateFile) ? json_decode(file_get_contents($this->stateFile), true) : [];
        $syncKey = $state['folderSyncKey'] ?? '0';
        $folders = $state['folders'] ?? [];

        if ($syncKey === '0') {
            $xml0 = '<?xml version="1.0" encoding="utf-8"?><FolderSync xmlns="FolderHierarchy"><SyncKey>0</SyncKey></FolderSync>';
            $dom0 = $this->request('FolderSync', $xml0);
            $syncKey = $this->getNodeValue($dom0, 'SyncKey', '0');
            $folders = []; // Reset folders on SyncKey 0
        }

        $xml = '<?xml version="1.0" encoding="utf-8"?><FolderSync xmlns="FolderHierarchy"><SyncKey>'.$syncKey.'</SyncKey></FolderSync>';
        $dom = $this->request('FolderSync', $xml);
        
        if (!$dom) return array_values($folders);

        $status = $this->getNodeValue($dom, 'Status', '1');
        if ($status === '9') {
            // Invalid/Expired Sync Key, Server requests a full reset
            $this->saveState('folderSyncKey', '0');
            return $this->getFolders();
        }

        $newSyncKey = $this->getNodeValue($dom, 'SyncKey', $syncKey);

        foreach ($this->getNodes($dom, 'Add') as $add) {
            $id = $this->getNodeValue($add, 'ServerId');
            $folders[$id] = [
                'id' => $id,
                'name' => $this->getNodeValue($add, 'DisplayName'),
                'unread' => 0,
                'delimiter' => '/'
            ];
        }
        
        foreach ($this->getNodes($dom, 'Delete') as $del) {
            unset($folders[$this->getNodeValue($del, 'ServerId')]);
        }

        foreach ($this->getNodes($dom, 'Update') as $upd) {
            $id = $this->getNodeValue($upd, 'ServerId');
            if (isset($folders[$id])) {
                $folders[$id]['name'] = $this->getNodeValue($upd, 'DisplayName');
            }
        }

        $this->saveState('folderSyncKey', $newSyncKey);
        $this->saveState('folders', $folders);

        return array_values($folders);
    }


    public function getMessages($folderId, $page = 1, $perPage = 50) {
        $state = file_exists($this->stateFile) ? json_decode(file_get_contents($this->stateFile), true) : [];
        $syncKey = $state['itemSyncKeys'][$folderId] ?? '0';
        $folderEmails = $state['emails_' . $folderId] ?? [];

        if ($syncKey === '0') {
            $initXml = '<?xml version="1.0" encoding="utf-8"?><Sync xmlns="AirSync"><Collections><Collection><Class>Email</Class><SyncKey>0</SyncKey><CollectionId>'.$folderId.'</CollectionId></Collection></Collections></Sync>';
            $initDom = $this->request('Sync', $initXml);
            $syncKey = $this->getNodeValue($initDom, 'SyncKey', '0');
            $this->updateSyncKeyAndCache($folderId, $initDom, function() { return []; });
            if ($syncKey === '0') return [];
            $folderEmails = [];
        }

        $xml = '<?xml version="1.0" encoding="utf-8"?><Sync xmlns="AirSync"><Collections><Collection><Class>Email</Class><SyncKey>'.$syncKey.'</SyncKey><CollectionId>'.$folderId.'</CollectionId><GetChanges>1</GetChanges><WindowSize>100</WindowSize><Options><FilterType>0</FilterType><BodyPreference xmlns="AirSyncBase"><Type>2</Type><TruncationSize>5120</TruncationSize></BodyPreference></Options></Collection></Collections></Sync>';
        $dom = $this->request('Sync', $xml);
        if (!$dom) goto RETURN_CACHE;

        $status = $this->getNodeValue($dom, 'Status', '1');
        $colStatus = $this->getNodeValue($this->getNodes($dom, 'Collection')[0] ?? null, 'Status', '1');
        if ($status === '3' || $colStatus === '3') { // Protocol: 3 = Invalid Sync Key (requires full reset)
            $this->saveState('itemSyncKeys', array_merge($state['itemSyncKeys'] ?? [], [$folderId => '0']));
            return $this->getMessages($folderId, $page, $perPage);
        }

        foreach ($this->getNodes($dom, 'Add') as $change) {
            $id = $this->getNodeValue($change, 'ServerId');
            $appData = $this->getNodes($change, 'ApplicationData')[0] ?? null;
            if (!$appData) continue;

            $from = $this->getNodeValue($appData, 'From', 'Unknown');
            $fromName = $from; $fromEmail = $from;
            if (preg_match('/^"([^"]*?)"\s*<([^>]+)>$/', $from, $m) || preg_match('/^([^<]*?)\s*<([^>]+)>$/', $from, $m)) {
                $fromName = trim($m[1]); $fromEmail = trim($m[2]);
            }
            $ts = ($d = $this->getNodeValue($appData, 'DateReceived')) ? strtotime($d) : time();
            $attachments = [];
            foreach ($this->getNodes($appData, 'Attachment') as $att) {
                $attachments[] = [
                    'name' => $this->getNodeValue($att, 'DisplayName'),
                    'fileReference' => $this->getNodeValue($att, 'FileReference'),
                    'method' => $this->getNodeValue($att, 'Method'),
                    'size' => $this->getNodeValue($att, 'EstimatedDataSize')
                ];
            }
            $folderEmails[$id] = [
                'id' => $id, 'folder' => $folderId, 'ts' => $ts,
                'subject' => $this->getNodeValue($appData, 'Subject', '(No Subject)'),
                'fromName' => $fromName, 'fromEmail' => $fromEmail,
                'to' => $this->getNodeValue($appData, 'To'),
                'cc' => $this->getNodeValue($appData, 'Cc'),
                'bcc' => $this->getNodeValue($appData, 'Bcc'),
                'reply_to' => $this->getNodeValue($appData, 'ReplyTo'),
                'date' => (date('Y-m-d', $ts) === date('Y-m-d')) ? date('H:i', $ts) : date('d M Y H:i', $ts),
                'is_read' => $this->getNodeValue($appData, 'Read', '0') === '1',
                'is_flagged' => $this->getNodeValue($this->getNodes($appData, 'Flag')[0] ?? null, 'Status', '0') === '2',
                'has_attachments' => count($attachments) > 0
           ];
        }

        foreach ($this->getNodes($dom, 'Change') as $change) {
            $id = $this->getNodeValue($change, 'ServerId');
            if (isset($folderEmails[$id]) && ($appData = $this->getNodes($change, 'ApplicationData')[0] ?? null)) {
                $r = $this->getNodeValue($appData, 'Read'); if ($r !== '') $folderEmails[$id]['is_read'] = ($r === '1');
                $f = $this->getNodeValue($this->getNodes($appData, 'Flag')[0] ?? null, 'Status'); if ($f !== '') $folderEmails[$id]['is_flagged'] = ($f === '2');
            }
        }

        foreach ($this->getNodes($dom, 'Delete') as $change) {
            unset($folderEmails[$this->getNodeValue($change, 'ServerId')]);
        }

        $this->updateSyncKeyAndCache($folderId, $dom, function() use ($folderEmails) { return $folderEmails; });

        if (count($this->getNodes($dom, 'MoreAvailable')) > 0) return $this->getMessages($folderId, $page, $perPage);

        RETURN_CACHE:
        $finalList = array_values($folderEmails);
        usort($finalList, function($a, $b) { return $b['ts'] <=> $a['ts']; });
        return array_slice($finalList, ($page - 1) * $perPage, $perPage);
    }

    public function getAttachment($fileReference) {
        $xml = '<?xml version="1.0" encoding="utf-8"?><ItemOperations xmlns="ItemOperations"><Fetch><Store>Mailbox</Store><FileReference>'.htmlspecialchars($fileReference).'</FileReference></Fetch></ItemOperations>';
        $dom = $this->request('ItemOperations', $xml);
        return $this->getNodeValue($dom, 'Data');
    }

    public function getMessageBody($folderId, $messageId) {
        // Sanitize identifiers to prevent XML injection payload manipulation
        $fId = htmlspecialchars((string)$folderId, ENT_XML1, 'UTF-8');
        $mId = htmlspecialchars((string)$messageId, ENT_XML1, 'UTF-8');
        $xml = '<?xml version="1.0" encoding="utf-8"?><ItemOperations xmlns="ItemOperations"><Fetch><Store>Mailbox</Store><CollectionId>'.$fId.'</CollectionId><ServerId>'.$mId.'</ServerId><Options><BodyPreference xmlns="AirSyncBase"><Type>2</Type></BodyPreference></Options></Fetch></ItemOperations>';
        $dom = $this->request('ItemOperations', $xml);
        
        $body = $this->getNodeValue($dom, 'Data');
        return ['html' => $body, 'plain' => strip_tags($body), 'attachments' => []];
    }

    public function sendEmail($to, $subject, $bodyHtml, $cc = '', $bcc = '') {
        $mime = "To: {$to}\r\n";
        if (!empty($cc)) $mime .= "Cc: {$cc}\r\n";
        if (!empty($bcc)) $mime .= "Bcc: {$bcc}\r\n";
        $mime .= "Subject: {$subject}\r\nContent-Type: text/html; charset=utf-8\r\n\r\n{$bodyHtml}";
        
        $xml = '<?xml version="1.0" encoding="utf-8"?><SendMail xmlns="ComposeMail"><ClientId>'.uniqid().'</ClientId><SaveInSentItems/><Mime>'.base64_encode($mime).'</Mime></SendMail>';
        $this->request('SendMail', $xml);
        return true;
    }

    public function markRead($folderId, $messageId, $isRead = true) {
        $readVal = $isRead ? '1' : '0';
        $fId = htmlspecialchars((string)$folderId, ENT_XML1, 'UTF-8');
        $mId = htmlspecialchars((string)$messageId, ENT_XML1, 'UTF-8');
        $xml = '<?xml version="1.0" encoding="utf-8"?><Sync xmlns="AirSync"><Collections><Collection><Class>Email</Class><SyncKey>'.$this->getSyncKey($folderId).'</SyncKey><CollectionId>'.$fId.'</CollectionId><Commands><Change><ServerId>'.$mId.'</ServerId><ApplicationData><Read>'.$readVal.'</Read></ApplicationData></Change></Commands></Collection></Collections></Sync>';
        $dom = $this->request('Sync', $xml);
        $this->updateSyncKeyAndCache($folderId, $dom, function($cache) use ($mId, $isRead) {
            if (isset($cache[$mId])) $cache[$mId]['is_read'] = $isRead;
            return $cache;
        });
    }

    public function markFlagged($folderId, $messageId, $isFlagged = true) {
        $status = $isFlagged ? '2' : '0'; // 2 = Active, 0 = Clear
        $fId = htmlspecialchars((string)$folderId, ENT_XML1, 'UTF-8');
        $mId = htmlspecialchars((string)$messageId, ENT_XML1, 'UTF-8');
        $xml = '<?xml version="1.0" encoding="utf-8"?><Sync xmlns="AirSync"><Collections><Collection><Class>Email</Class><SyncKey>'.$this->getSyncKey($folderId).'</SyncKey><CollectionId>'.$fId.'</CollectionId><Commands><Change><ServerId>'.$mId.'</ServerId><ApplicationData xmlns:m="Email"><m:Flag><m:Status>'.$status.'</m:Status></m:Flag></ApplicationData></Change></Commands></Collection></Collections></Sync>';
        $dom = $this->request('Sync', $xml);
        $this->updateSyncKeyAndCache($folderId, $dom, function($cache) use ($mId, $isFlagged) {
            if (isset($cache[$mId])) $cache[$mId]['is_flagged'] = $isFlagged;
            return $cache;
        });
    }

    public function deleteMessage($folderId, $messageId) {
        $fId = htmlspecialchars((string)$folderId, ENT_XML1, 'UTF-8');
        $mId = htmlspecialchars((string)$messageId, ENT_XML1, 'UTF-8');
        $xml = '<?xml version="1.0" encoding="utf-8"?><Sync xmlns="AirSync"><Collections><Collection><Class>Email</Class><SyncKey>'.$this->getSyncKey($folderId).'</SyncKey><CollectionId>'.$fId.'</CollectionId><Commands><Delete><ServerId>'.$mId.'</ServerId></Delete></Commands></Collection></Collections></Sync>';
        $dom = $this->request('Sync', $xml);
        $this->updateSyncKeyAndCache($folderId, $dom, function($cache) use ($mId) {
            unset($cache[$mId]);
            return $cache;
        });
    }

    public function moveMessage($msgId, $srcFolder, $destFolder) {
        $mId = htmlspecialchars((string)$msgId, ENT_XML1, 'UTF-8');
        $sId = htmlspecialchars((string)$srcFolder, ENT_XML1, 'UTF-8');
        $dId = htmlspecialchars((string)$destFolder, ENT_XML1, 'UTF-8');
        $xml = '<?xml version="1.0" encoding="utf-8"?><MoveItems xmlns="Move"><Move><SrcMsgId>'.$mId.'</SrcMsgId><SrcFldId>'.$sId.'</SrcFldId><DstFldId>'.$dId.'</DstFldId></Move></MoveItems>';
        $this->request('MoveItems', $xml);
    }
}

