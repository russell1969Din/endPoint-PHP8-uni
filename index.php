<?
//Koncový bod užívate¾a, poskytujúci vo formáte JavaScript Object Notation (JSON) požadované dáta z poskytnutej databázy
$endPoint = new EndPoint();
echo $endPoint->command();

class EndPoint {
    
    //Pri ladení kódu protect = false, inak sa rovná true
    const protect   = false;
    
    //Pri ladení kódu môžeme zada pracovný SQL
    const SQL       = 'SELECT * FROM 0vr2r_sysUserAccounts, 0vr2r_sysVisitorsIn';

    //Deklarácia tabu¾ky pre kontrolu povolenia prístupu vzdialeného úèastníka
    const accessUsersTable  = '000aaa_USER_ENDPOINT';
    const prefixUsersTable  = 'uep';
    
    //Deklarácia štruktúry tabu¾ky pre kontrolu povolenia prístupu vzdialeného úèastníka
    const accessUsersFields = array('name',    'pass',    'serial',  'active', 'validUntil', 'jurisdiction');  
    const accessUsersTypes  = array('VARCHAR(30)', 'VARCHAR(30)', 'VARCHAR(30)', 'BOOLEAN', 'DATE', 'SMALLINT');
    const accessUsersRecord = array('name'=>'userEndPoint', 'pass'=>'enigma', 'serial'=>'123-ABC', 'active'=>'1', 'jurisdiction'=>0);

    //Konštruktor triedy
    function __construct() {

        //Definícia akceptovate¾ného dia¾kového prístupu užívate¾a pre tento koncový bod
        header("Access-Control-Allow-Origin: http://localhost:3000");    
        
        //Pri ladení kódu vytvoríme doèasný "protection"
        if(!self::protect) {$_POST["protection"] = 'ABNet';}
        
        //Pri ladení kódu vytvoríme doèasné parametre pre prístup
        if(!self::protect) {$_POST["user"] = 'userEndPoint';}
        if(!self::protect) {$_POST["pass"] = 'enigma';}
        if(!self::protect) {$_POST["serial"] = '123-ABC';}
        
        
        //PHP8+ v každom prípade vyžaduje deklaráciu POST premenných aj keï ako prázdny reazec
        if(!isSet($_POST["protected"])) $_POST["protected"] = '';
        
        //Pri ladení kódu ak nemáme poskytnuté SQL nastavíme pracovné SQL
        if(!self::protect) {if(!isSet($_POST["SQL"])) $_POST["SQL"] = self::SQL;}
        if(!self::protect && strLen(Trim($_POST["SQL"]))==0) {$_POST["SQL"] = self::SQL;}

        //Ak nie sme v režime ladenia, a klient je iný ako "Access-Control-Allow-Origin"
        //Funkènos kodu bude zastavená
        if(!isSet($_POST["protection"])) {die();} else {if($_POST["protection"]!='ABNet') {die();}}

        //Zistíme parametre pre pripojenie sa ku databáze
        include('dbAccess.php');
        
        //Pripojíme sa na poskytnutú databázu pod¾a zistených parametrov
        $this->con = @MySQLi_connect($this->dbLocal, $this->dbLogin, $this->dbPass,  $this->dbName);

        //Ak nastala v pripájaní na databázu chyba, a poskytnutá databáza nie je dostupná 
        if (mysqli_connect_errno()) {
            //koncový bod vráti reazec reprezentujúci objekt s chybovou správou v položke "error"
            $this->con = '[{"error":"'.mysqli_connect_error().'"}]'; 
        } else {
            //Kontrola èi vzdialený úèasník má oprávnenie aby prevzal objekt 
            //s požadovanými  informáciami prevzatými z databázy
            $this->userAccess();
        }
        
    }
    
    //Pod¾a SQL príkazu zavoláme príslušnú privátnu metódu triedy na spracovanie SQL
    public function command() {
    
        //Ak nastala v pripájaní na databázu chyba, a poskytnutá databáza nie je dostupná 
        //koncový bod vráti reazec reprezentujúci objekt s chybovou správou v položke "error"
        if(getType($this->con) == 'string') {return $this->con;}
            
        //Zavoláme privátnu metódu príslušnú k spracovaniu aktuálneho SQL príkazu
        eval('$JSON = $this->'.$this->isSQLCommand().'();');
        
        //Vrátime získaný Javascript Object Notation
        return $JSON;
    }
    
    //Privátna metóda triedy spracuje SQL príkaz SELECT[...]
    private function select() {
    
        $JSON = '[]';
        //Inicializujem pole do ktorého naèítam štruktúry tabuliek ako ich používa doruèené SQL
        $aStructure = array();
        
        //Listujem tabu¾ky z doruèeného SQL príkazu
        foreach($this->getTables() as $tableName) {
            //a štruktúry z tabuliek z doruèeného SQL príkazu pridávam do po¾a $aStructure 
            $aStructure = $this->structure($tableName, $aStructure);
        }

        //Naèítam obsah tabuliek parametrom poskytnutého SQL príkazu
        if($records = MySQLi_query($this->con, $_POST["SQL"])) {
            //Zaèiatok JSON v rámci reazca
            $JSON = '[';
            //Listujem v riadkoch tabuliek pod¾a poskytnntého SQL
            while($line = MySQLi_Fetch_Array($records, MYSQLI_ASSOC))   {
                //Zaèiatok vety v JSON v rámci reazca
                $JSON .= '{';
                foreach($aStructure as $field) {
                    //Vkladám do JSON novú položku s údajom prevzatým z databázy 
                    $JSON .= '"'.$field.'":"'.$line[$field].'",';
                }
                //Koniec vety v JSON v rámci reazca s odrezaním poslednej èiarky
                $JSON = subStr($JSON, 0, strLen($JSON)-1);
                $JSON .= '},';
            }
            //Koniec obsahu JSON objektu v rámci reazca s odrezaním poslednej èiarky
            $JSON = subStr($JSON, 0, strLen($JSON)-1);
            $JSON .= ']';
        }

        //JSON objekt s požadovanými údajmi z databázy táto metóda triedy vráti pre odovzdanie 
        //vzdialenému užívate¾ovi
        return $JSON;
    }
    
    //Privátna metóda zistí, èi SQL príkaz je pre koncový bod spravovate¾ný
    private function isSQLCommand() {
        //Do premennej $param naèíta príkaz z SQL
        $param = strToLower(trim(subStr(trim($_POST["SQL"]), 0, strPos($_POST["SQL"], ' '))));
        
        //Ak zistený príkaz z SQL je koncový bod schopný spracova, vráti názov na príslušnej to privátnej metódy triedy
        $command = match($param) {
            'select', 'insert'  => $param,
            default => 'emptyObject',
        };
        
        //Vráti názov na príslušnej to privátnej metódy triedy, alebo triedy ktorá vráti prázdny objekt
        return($command);        
    }
    
    //Ak SQL neobsahuje príkaz akceptovate¾ný pre koncový bod, touto metódou vráti prázdny objekt 
    private function emptyObject() {return '[]';}
    
    //Vráti pole s názvami tabuliek, ako ich obsahuje koncovému bodu poskytnutý SQL príkaz
    private function getTables() {
    
        //Zistím kde sa v SQL nachádza rezervovaný výraz FROM
        $from = strPos(strToLower($_POST["SQL"]), ' from ');
        
        //Ak rezervovaný výraz FROM v SQL nenájdem, vrátim prázdne pole
        if(!$from) return array();
        
        //Ostránim èas SQL príkazu pred definíciou názvov tabuliek
        $SQL = substr($_POST["SQL"], $from+5, strLen(Trim($_POST["SQL"])));

        //Zostávajúci SQL reazec premením na pole s názvami tabuliek a zvyšným nepotrebným kódom
        $aTables = explode(',', $SQL);
        
        //Inicializujem návratové pole
        $returnTables = array();
        
        //Listujem zistené názy tabuliek aj s prebytoèným kódom
        foreach($aTables as $table) {
            //Orežem názov tabu¾ky ak má medzery
            $table = trim($table);
            
            //Zistím èi v názne tabu¾ky neostala medzera, èo by znamenalo že názov obsahuje prebytoèný kód
            $space = strPos($table, ' ');
            
            //Ak v názve tabu¾ky prebytoèný kód ostal zachovaný odrežem ho
            if($space) $table = subStr($table, 0, $space+1);
            
            //Do návratováho po¾a pridám èistý názov tabu¾ky bez rušivých znakov
            $returnTables[] = $table;
        }

        //Aktuálna metóda vráti pole so spracovanými názvami tabuliek ako ich obsahoval SQL 
        //poskytnutý koncovému bodu
        return $returnTables;
    }

    //Privátna metóda naèíta názvy položiek zo štruktúry tabu¾ky v parametri, pridá ich do paramera $aStucture
    //a tento parameter vráti 
    private function structure($table, $aStucture=array()) {

        //Vytvorím SQL príkaz pre naèítanie prvého riadku z SQL tabu¾ky s názvom doruèeným v parametri metódy
        $SQL = "SELECT * FROM $table LIMIT 1";

        //Naèítam prvý riadok z SQL tabu¾ky  s názvom doruèeným v parametri metódy
        if($records = $this->con->query($SQL)) {
            //Naèítam +strukt=uru tabu¾ky  s názvom doruèeným v parametri metódy
            $structure = $records->fetch_fields();
    
            //Listujem struktúru tabu¾ky z parametra
            foreach ($structure as $field) {
                //a aktuálny názov po¾a tabu¾ky vkladám do návratového po¾a
                $aStucture[] = $field->name;
            }
        } else {
            // to do   
        }
        
        //Vrátim návratové pole s názvami jehnotlivých položiek štruktúry tabu¾ky
        return $aStucture;
    }
    
    //Metóda triedy pre prácu s prístupovou tabu¾kou 
    private function userAccess() {
    
        //Pripravím SQL príkaz pre naèítanie tabu¾ky s prís¾ubmi povolených prístupov
        //to do doplni podmienku z POST parametrov
        //name',    'pass',    'serial',  'active', 'validUntil', 'jurisdiction'
        $prx = self::prefixUsersTable;
        $SQL = 'SELECT * FROM '.self::accessUsersTable.' WHERE '   .$prx.'_name="'.$_POST["user"].'" && '.
                                                                    $prx.'_pass="'.$_POST["pass"].'" && '.
                                                                    $prx.'_serial="'.$_POST["serial"].'" && '.
                                                                    $prx.'_active=1';
        //Ak naèítanie prebehne v poriadku
        if($records = $this->con->query($SQL)) {                    
        
            //Zistím èi je vzdialený užívate¾ oprávnený použiva tento koncový bod
            if(MySQLi_Num_Rows($records)>0) {
                //Ak áno
                echo ' OK ';
            } else {
                //Ak nie
                echo ' ACCESS DENIED ';
            }

        } else {
            echo 'ERROR ACCESS';
            //Ak sa tabu¾ku s prístupovými právami nepodarilo naèíta, pokúsim sa ju vytvori
            $isCreate = $this->createUserAccess();
            
            //Ak som ju vytvoril a som b režime ladenia zdrojového kódu
            if(!self::protect && $isCreate) {
                
                //Inicializujem triedu s internými SQL príkazmi
                // to do: upravi parametre medzi konštruktorom a parametrami metód
                $internalSQL = new InternalSQL( array(  'con'=>$this->con,
                                                        'protect'=>self::protect,
                                                        'tableName'=>self::accessUsersTable,
                                                        'aInsert'=>self::accessUsersRecord,
                                                        'prefix'=>self::prefixUsersTable));
                
                //Ak sa podarí vloži prvý záznam vytvorený systémom rekurzívne opä zavolám túto metódu 
                if($internalSQL->insert()) $this->userAccess();
            }
            return false;
       }
    }
    
    //Privátne metóda, ktorá vytvorí tabu¾ku prístupov kku koncovému bodu
    private function createUserAccess() {
        
        //Nazov systémovej tabu¾ky povolených prístupov naèítame z konštanty triedy
        $aTable     = array(self::accessUsersTable);
        
        //Polia názvov položiek a ich typov v systémovej tabu¾ke tiež naèítame z konštánt triedy
        $aFields    = self::accessUsersFields;
        $aTypes     = self::accessUsersTypes;
        
        //Inicializujem triedu s internými SQL príkazmi
        // to do: upravi parametre medzi konštruktorom a parametrami metód
        $internalSQL = new InternalSQL( array(  'con'=>$this->con,
                                                'protect'=>self::protect,
                                                'aTable'=>$aTable,
                                                'aFields'=>$aFields,
                                                'aTypes'=>$aTypes,
                                                'prefix'=>self::prefixUsersTable));        
        
        //Z triedy interných SQL príkazov zavoláme metódu pre vytvorenie systémovej tabu¾ky prístupov
        return $internalSQL->createTable();
    }
}

//Trieda s metódami pre internú tvorbu SQL príkazov a ich použitie
class InternalSQL {
    
    function __construct($aParams) {

        //V konštruktore naèítame parametre triedy z pola s klúèmi reprezentujúcimi názvy premenných
        //inicializujem ich ako interné premenné triedy
        foreach(array_keys($aParams) as $key) eval("\$this->".$key." = \$aParams[\$key];");
    }
    
    //Interná metóda vytvárajúca novú MySQL tabu¾ku
    public function createTable() {     //to do: dopracova parametre do pola s klúèmi reprezentujúcimi názvy premenných

        //Kontrola parametrov, èi sú v poriadku a fatálne nezastavia chod metódy
        if( count(self::arrayControl($this->aTable)) == 0 ||
            count(self::arrayControl($this->aFields)) == 0 ||
            count(self::arrayControl($this->aTypes)) == 0)  return false;

        if(count(self::arrayControl($this->aFields)) != count(self::arrayControl($this->aTypes)))
            return false;
            
        //Inicializujeme nový konštruktor pre pracu s prefixom tabuliek
        $prefix = new Prefix();
        
        //Zistíme poèet novýchh položiek, ktoré bude obsahova nová tabu¾ka    
        $countFields = count(self::arrayControl($this->aFields));
        
        //Pokúsime sa zisti z názvov položiek nastavený prefix novej tabu¾ky
        $prx = $prefix->getPrefix($this->aFields[0]);
        //Ak sme ho nezistili preberieme ho  z parametra konštruktora triedy
        if(strLen(Trim($prx))==0) $prx = $this->prefix;

        //Pracovná premenná pre vytvorenie jednoznaèného indexovaného k¾úèa
        $indexKey = '';
        
        //Zaèiatok reazca prezentujúceho SQL príkaz na vytvorenie novej MySQL tabu¾ky
        $SQL = "CREATE TABLE IF NOT EXISTS ".$this->aTable[0]."(";
        
        //Premenná indikujúca prvý záznam po¾a položiek novej MySQL tabu¾ky 
        $first = true;
        
        //Listovanie záznamov po¾a položiek novej MySQL tabu¾ky 
        for($index=0;$index<$countFields;++$index) {
            //Ak sme v prvom kole cyklu listovania
            if($first) {
                //Pridáme prvú položku id s prefixom, ktorá bude unikátna a indexovaná v databáze
                $SQL .= $this->prefix."_id BIGINT unsigned AUTO_INCREMENT NOT NULL, ";    
                
                //Nastavíme premennú pre vytvorenie jednoznaèného indexovaného k¾úèa
                $indexKey = ",  PRIMARY KEY (".$this->prefix."_id)";
                
                //Ïalšie kolo tohto cyklu už nebude prvé
                $first = !$first;
            }
            //Vložíme z parametra príslušnú novú položku k vytváranej tabu¾ke, jej typ a ïalšie vlastnosti
            $SQL .= $prefix->withPrefix($this->aFields[$index], $prx)." ".$this->aTypes[$index]." COLLATE utf8_slovak_ci NOT NULL, ";
        }
        
        //Orežeme poslednú èiarku, pridáme premennú s obsahom vzahujúcim sa na indexvanú 
        //a unikátnu položku a SQL Príkaz uzavrieme
        $SQL = subStr($SQL, 0, strLen(Trim($SQL))-1)." $indexKey)";
        
        //Ku SQL príkazu na vyvorenie tabu¾ky doplníme ïalšie vlastnosti
        $SQL .= " ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_slovak_ci AUTO_INCREMENT=1;";

        //Ak sa podarí tabu¾ku vytvori, táto metóda vráti true
        if ($this->con->query($SQL) === true) return true; else {
            
            //Ak nie ... vráti false
            echo "ERROR ::: ".$this->con->error;
            return false;
        }
    
    }
   
    //Interná metóda pridávajúca nový záznam do existujúcej MySQL tabu¾ky
    public function insert() {

        //Kontrola parametrov, èi sú v poriadku a fatálne nezastavia chod metódy
        if(getType($this->tableName)!='string') return false;
        if(strLen(Trim($this->tableName))==0) return false;
        
        if(getType($this->aInsert)!='array') return false;
        if(count($this->aInsert)==0) return false;
        
        foreach(array_keys($this->aInsert) as $key) {
            if(strLen(Trim($key))==0) return false;
            break;
        }
   
        //Inicializujeme nový konštruktor pre pracu s prefixom tabuliek
        $prefix = new Prefix();

        //Premenná indikujúca prvý záznam po¾a položiek novej MySQL tabu¾ky        
        $first = true;
        
        //Zaèiatok reazca prezentujúceho SQL príkaz na vloženie nového záznamu 
        //do existujúcej  MySQL tabu¾ky
        $SQL = "INSERT INTO $this->tableName (";
        
        //Listovanie záznamov s ktorými má SQL príkaz v MySQL tabu¾ke pracova
        foreach(array_keys($this->aInsert) as $key) {
            
            //Ak sme v prvom kole cyklu listovania
            if($first) {

                //Pokúsime sa zisti z názvov položiek nastavený prefix novej tabu¾ky
                $prx = $prefix->getPrefix($key); 
                //Ak sme ho nezistili preberieme ho  z parametra konštruktora triedy
                if(strLen(Trim($prx))==0) $prx = $this->prefix;

                //Ïalšie kolo tohto cyklu už nebude prvé
                $first = !$first;}
                
            //Postupne naèítam z po¾a v parametri všetky názvy položiek MySQL tabu¾ky
            //ktoré budú používané pri pridávaní nového záznamu
            $SQL .= $prefix->withPrefix($key, $prx).",";
        }

        //Ideme naèíta hodnoty do nového záznamu MySQL tabu¾ky
        $SQL = subStr($SQL, 0, strLen(Trim($SQL))-1).") VALUES(";
        foreach(array_keys($this->aInsert) as $key) $SQL .= "'".$this->aInsert[$key]."',";
    
        //Orežeme poslednú èiarku a SQL príkaz na pridanie záznamu uzavrieme zátvorkou
        $SQL = subStr($SQL, 0, strLen(Trim($SQL))-1).")";

        //Ak bol záznam úspešne pridaný metóda vráti true
        if ($this->con->query($SQL) === true) return true; else {
            //Ak nie ...  vráti false
            echo "ERROR ::: ".$this->con->error;
            return false;
        }
   }
   
   //to do: Dopracova komentáre
   private function arrayControl($param) {
        if(getType($param)=='string') {
            if(strLen(Trim($param))==0) return array();
            return array($param);
        }
        
        if(getType($param)!='array') return array();
        return $param;
    }
}


//to do: Dopracova komentáre
class Prefix {
    
    function __construct() {}

    public function getPrefix($fieldName) {
        if(strPos($fieldName,'_')) return subStr($fieldName, 0, strPos($fieldName,'_')-1);
        return '';
    }
    
    public function withPrefix($fieldName, $prefix='') {
        
        if(strLen(Trim($prefix))>0) $prefix = $prefix."_";
        if(!strPos($fieldName,'_')) return "$prefix$fieldName";
        return $fieldName;
    }

}
