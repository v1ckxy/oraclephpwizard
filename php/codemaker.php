<?php

require_once("autoproccaller.php");

class Codemaker {
  //=====================================================================
  // Some globals

  private $init = false;
  private $havecursors;
  private $curs = array();
  private $outs = array();
  private $declvarsize = array();
  private $ins = array();
  private $insize = array();
  private $instub = array();
  private $outstub = array();
  private $allparams = array();
  private $filename = "";
  private $classname = "";
  //==================================================================
  // Here we go...
  //------------------------------------------------------------------

  /*******************************************************************
   * Returns the begining of the class
   * Creates class declaration, attributes and constructor
   */
  private function start_of_class( $classname ) {
    if ( ! $this->init ){
      return ('Run make_class first');
    }

    if ( CLASSNAME_INITCAP ) {
      $this->classname = ucfirst( strtolower( $classname ) );
    } else {
      $this->classname = $classname;
    }

    //--start concat
    $retval = 'require_once("' . DEFSIZELOCATION . '");' . NL . BLANKLINE;
    $retval .= "class " . $this->classname . " {" . NL . BLANKLINE;
    $retval .= IND1 . 'private $dbconn;' . NL;
    $retval .= IND1 . 'private $invars;' . NL;
    $retval .= IND1 . 'private $outvars = array();' . NL;
    $retval .= IND1 . 'private $errorcode = 0;' . NL;
    $retval .= IND1 . 'private $errmess = "";' . NL;
    if ( $this->curs ) $retval .= IND1 . 'private $curs;' . NL;
    $retval .= BLANKLINE;

    foreach ( $this->declvarsize as $outlen ) {
      $retval .= IND1 . 'public ' . $outlen . NL;
    }

    $retval .= NL . IND1 . 'public function __construct( $dbconn, &$invars ) {' . NL;
    $retval .= IND2 . '$this->dbconn = $dbconn;' . NL;
    $retval .= IND2 . '$this->invars = &$invars;' . NL;

    $retval .= IND1 . '}' . NL;
    $retval .= BLANKLINE;

    //-- end of concat
    return $retval;
  }

  /*******************************************************************
   * Creates exec method
   */
  private function middle_of_class ( $as_pack, $as_proc ) {
    if ( ! $this->init ){
      return ('Run make_class first');
    }

    // concat argnames as :arg1,:arg2,:arg3,
    $argnames = '';
    foreach( $this->allparams as $param ) {
      $argnames .= ':' . $param . ',';
    }
    // cut of last comma
    if ( $argnames != '' ) {
      $argnames = rtrim( $argnames, ',' );
    }

    if ($as_pack) {
      $lprocname = $as_pack . '.' . $as_proc;
    } else {
      $lprocname = $as_proc;
    }

    //-- start of concat
    $retval = IND1 . 'public function exec() {'. NL;
    $retval .= IND2 . '$this->set_error( 0, "");'. NL;
    $retval .= IND2 . '$retval = true;'. NL;

    $retval .= IND2 . '$psqlstmnt = "Begin ' . $lprocname . '(';
    $retval .= $argnames . ');end;";' . NL;
    $retval .= BLANKLINE;

    $retval .= IND2 . '$stmt = oci_parse( $this->dbconn, $psqlstmnt );' . NL;
    $retval .= BLANKLINE;

    foreach ( $this->ins as $idx=>$input ) {
      $retval .= IND2 . "oci_bind_by_name( \$stmt, ':" . $input . "', \$this->invars[ '" . $input . "' ], " . $this->insize[ $idx ] . " );". NL;
    }
    $retval .= BLANKLINE;

    foreach( $this->outs as $out ) {
      $retval .= IND2 . "oci_bind_by_name( \$stmt, ':" . $out . "', \$this->outvars[ '" . $out . "' ], \$this->len" . $out . " );". NL;
    }
    $retval .= BLANKLINE;

    foreach ( $this->curs as $curs ) {
      $retval .= IND2 . "\$this->curs['" . $curs . "'] = oci_new_cursor( \$this->dbconn );". NL;
      $retval .= IND2 . "oci_bind_by_name( \$stmt, ':" . $curs . "', \$this->curs['" . $curs . "'], -1, OCI_B_CURSOR );". NL;
    }
    $retval .= BLANKLINE;

    $retval .= IND2 . 'if ( ! oci_execute( $stmt ) ) {'. NL;
    $retval .= IND3 . '$e = oci_error($stmt);'. NL;
    $retval .= IND3 . '$retval = false;'. NL;

    foreach ( $this->curs as $curs ) {
      $retval .= IND2 . "} elseif ( ! oci_execute( \$this->curs[ '" . $curs . "' ] ) ) {". NL;
      $retval .= IND3 . "\$e = oci_error( \$this->curs[ '" . $curs . "' ] );". NL;
      $retval .= IND3 . '$retval = false;' . NL;
    }

    $retval .= IND2 . '}'. NL;
    $retval .= BLANKLINE;

    $retval .= IND2 . 'oci_free_statement( $stmt );'. NL;
    $retval .= BLANKLINE;

    $retval .= IND2 . "if ( ! \$retval ) {". NL;
    $retval .= IND3 . "\$this->set_error( \$e['code'], \$e['message'] );". NL;
    $retval .= IND2 . '}'. NL;
    $retval .= BLANKLINE;

    $retval .= IND2 . 'return( $retval );'. NL;

    $retval .= IND1 . '}'. NL;
    $retval .= BLANKLINE;
    //-- end of concat

    return( $retval );
  }

  /*******************************************************************
   * Creates the getter and setter methods
   */
  private function end_of_class () {
    if ( ! $this->init ){
      return ('Run make_class first');
    }
    //-- start of concat
    $retval = IND1 . 'public function get_output( $param ) {' . NL;
    $retval .= IND2 . 'return ( $this->outvars[ $param ] );' . NL;
    $retval .= IND1 . '}' . NL;
    $retval .= BLANKLINE;

    $retval .= IND1 . 'public function get_curs( $argname ) {' . NL;
    $retval .= IND2 . 'return ( oci_fetch_assoc( $this->curs[ $argname ] ) );' . NL;
    $retval .= IND1 . '}' . NL;
    $retval .= BLANKLINE;

    $retval .= IND1 . 'public function free_curs() {' . NL;
    $retval .= IND2 . 'foreach ( $this->curs as &$cur ) {' . NL;
    $retval .= IND3 . 'oci_free_statement( $cur );' . NL;
    $retval .= IND2 . '}' . NL;
    $retval .= IND1 . '}' . NL;
    $retval .= BLANKLINE;

    $retval .= IND1 . 'private function set_error( $code, $message ) {' . NL;
    $retval .= IND2 . '$this->errorcode = $code;' . NL;
    $retval .= IND2 . '$this->errmess = $message;' . NL;
    $retval .= IND1 . '}' . NL;
    $retval .= BLANKLINE;

    $retval .= IND1 . 'public function get_errcode() {' . NL;
    $retval .= IND2 . 'return( $this->errorcode );' . NL;
    $retval .= IND1 . '}' . NL;
    $retval .= BLANKLINE;

    $retval .= IND1 . 'public function get_errmsg() {' . NL;
    $retval .= IND2 . 'return( htmlentities( $this->errmess, ENT_QUOTES ) );' . NL;
    $retval .= IND1 . '}' . NL;

    /* guess this is redundant since invars is a a ref
       TODO test update invars and run again
    $retval .= NL . IND1 . 'public function update_invars( &$invars ) {' . NL;
    $retval .= IND2 . '$this->invars = &$invars;' . NL;
    $retval .= IND1 . '}' . NL;
    */
    $retval .= '}' . NL;
    //-- end of concat

    return ($retval);
  }

  //------------------------------------------------------------------
  function make_class( $as_pack, $as_proc, $verbose) {

    $this->verbose = $verbose;

    $apc = new AutoProcCaller();

    if ( ! $apc->connectdb() ) {
      return ( "Failed to connect db!" );
    }

    $lserr = "";
    $lierr = $apc->get_params( $as_pack, $as_proc, $lparams, $lserr );

    if ( $lierr != 0 ) {
      return ( "Failed to get parameters." . $lserr );
    }

    $this->havecursors = false;
    $idx = 0;

    while ( $entry = oci_fetch_assoc ( $lparams ) ) {

      $this->allparams[ $idx ] = $entry[ 'ARGUMENT_NAME' ];
      if ( $entry[ 'DATA_TYPE' ] == 102 ) {
        $this->curs[ $idx ] = $entry[ 'ARGUMENT_NAME' ];
        $this->havecursors = true;

      } elseif ( $entry[ 'IN_OUT' ] == 'OUT' ) {
        $this->outs[ $idx ] = $entry[ 'ARGUMENT_NAME' ];
        $this->declvarsize[ $idx ] = '$len' . $entry[ 'ARGUMENT_NAME' ];
        $this->declvarsize[ $idx ] .= ' = ' . ORATYPESIZEPREFIX . $this->get_size_of_constantants( $entry[ 'DATA_TYPE' ] ) . ';';
        $this->outstub[ $idx ] = "echo \$proc->get_output( '" . $entry[ 'ARGUMENT_NAME' ] . "' ); // " . $this->get_size_of_constantants( $entry[ 'DATA_TYPE' ] );

      } elseif ( $entry[ 'IN_OUT' ] == 'IN/OUT' ) {
        $this->ins[ $idx ] = $entry[ 'ARGUMENT_NAME' ];
        $this->declvarsize[ $idx ] = '$len' . $entry[ 'ARGUMENT_NAME' ];
        $this->declvarsize[ $idx ] .= ' = ' . ORATYPESIZEPREFIX . $this->get_size_of_constantants( $entry[ 'DATA_TYPE' ] ) . ';';
        $this->insize[ $idx ] = '$this->len' . $entry[ 'ARGUMENT_NAME' ];
        $this->outstub[ $idx ] = "echo \$inargs[ '" . $entry[ 'ARGUMENT_NAME' ] . "' ]; // " . $this->get_size_of_constantants( $entry[ 'DATA_TYPE' ] ) . ' (IN/OUT)';
        $this->instub[ $idx ] = "\$inargs[ '" . $entry[ 'ARGUMENT_NAME' ] . "' ] = \$value; // " . $this->get_size_of_constantants( $entry[ 'DATA_TYPE' ] );

      } elseif ( $entry[ 'IN_OUT' ] == 'IN' ) {
        $this->ins[ $idx ] = $entry[ 'ARGUMENT_NAME' ];
        $this->insize[ $idx ] = '-1';
        $this->instub[ $idx ] = "\$inargs[ '" . $entry[ 'ARGUMENT_NAME' ] . "' ] = \$value; // " . $this->get_size_of_constantants( $entry[ 'DATA_TYPE' ] );
      }
      $idx++;
    }
    $this->init = true;

    if ( $as_pack ) {
      $classname = $as_pack . '__' . $as_proc;
    } else {
      $classname = $as_proc;
    }

    $retval = $this->start_of_class( $classname );
    $retval .= $this->middle_of_class ( $as_pack, $as_proc );
    $retval .= $this->end_of_class();

    return( $retval );
  }

  /*******************************************************************
   * Creates an example of how to use the class
   * If you're gonna write the class to file do that first
   */
  public function make_skeleton() {
    if ( ! $this->init ){
      return ('Run make_class first');
    }
    // stub starts here
    if  ( $this->filename ) {
      $retval = IND1 . 'require_once( "' . $this->filename . '" );' . SNL . SBLANKLINE;
    } else {
      $retval = '';
    }

    foreach ( $this->instub as $input ) {
      $retval .= IND1 . $input . SNL;
    }
    if ( ! empty( $this->instub ) ) {
      $retval .= SBLANKLINE;
    }

    $retval .= IND1 . "\$proc = new " . $this->classname. "( \$dbconn, \$inargs );". SNL . SBLANKLINE;

    $retval .= IND1 . "if ( ! \$proc->exec() ) {". SNL;
    $retval .= IND2 . "echo 'Fail: ' . get_errcode() . ' ' . get_errmsg();". SNL;
    $retval .= IND1 . "} else {". SNL;
    $retval .= SBLANKLINE;

    foreach ( $this->outstub as $output ) {
      $retval .= IND2 . $output . SNL;
    }
    if ( ! empty( $this->outstub ) ) {
      $retval .= SBLANKLINE;
    }

    foreach ( $this->curs as $curs ) {
      $retval .= IND2 . "while ( \$row = \$proc->get_curs( '" . $curs . "' ) ) {" . SNL;
      $retval .= IND3 . 'print_r( $row ); // $row[ $argname ]' . SNL;
      $retval .= IND2 . '}' . SNL . SBLANKLINE;
    }
    if ( $this->curs ) {
      $retval .= IND2 . '$proc->free_curs();'. SNL . SBLANKLINE;
    }

    $retval .= IND1 . '}' . SNL;
    // Stub ends here
    return( $retval );
  }

  //------------------------------------------------------------------
  public function write_to_file( $path, $fname, $content, $overwrite ) {
    if ( ! $this->init ){
      return ( -10 ); // Run make_class first
    }

    if ( ! $fname ) {
      return ( -100 ); // No file name
    }

    if ( strlen( $path ) ) {
      $path = rtrim( $path, DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR;
    } else {
      return ( -110 ); // Directory not set
    }

    if ( FILENAME_LCASE ) {
      $fname = strtolower( $path . $fname );
    }

    $this->filename = $fname;

    if ( ! file_exists( $path ) ) {
      $retval = -200; // path does not exist
    //} elseif ( is_writable( $path ) ) { // always false, is SE linux messing?
    //  $retval = -210; // directory is not writeable
    } elseif ( ! $overwrite && file_exists( $fname ) ) {
      $retval = -220; // overwrite is false and file exist
    } elseif ( file_exists( $fname ) && ! is_writable ( $fname )) {
      $retval = -230; // file exist but is not writeable
    } else {

      // Make it a php-file
      $content = '<?php' . NL . BLANKLINE . $content;
      $content .= NL . '?>';

      $result = file_put_contents ( $fname , $content );
      //print_r(error_get_last());
      if ( $result === false ) {
          $retval = -200;
      } else {
          $retval = 0;
      }
    }
    return( $retval );
  }

  //------------------------------------------------------------------
  public function get_size_of_constantants ( $type ) {

    if ( $type == 1 ) {
      $retval="ORATYPE_VARCHAR2";
    } elseif ( $type == 2 ) {
      $retval="ORATYPE_NUMBER";
    } elseif ( $type == 3 ) {
      $retval="ORATYPE_SINT";
    } elseif ( $type == 4 ) {
      $retval="ORATYPE_FLOAT";
      //8 = LONG ?
    } elseif ( $type == 9 ) {
      if ($this->verbose) {
        $retval="ORATYPE_VARCHAR // WARNING: Might be 'NCHAR VARYING'";
      } else {
        $retval="ORATYPE_VARCHAR";
      }
    } elseif ( $type == 11 || $type == 69 || $type == 104 ) {
      $retval="ORATYPE_ROWID";
    } elseif ( $type == 12 ) {
      $retval="ORATYPE_DATE";
    } elseif ( $type == 23 ) {
      $retval="ORATYPE_RAW";
      //24 = LONG_RAW ?
      //29 = BINARY_INTEGER --> ORATYPE_SIGNED32 ?
    } elseif ( $type == 96 ) {
      if ($this->verbose) {
        $retval="ORATYPE_CHAR // WARNING: Might be 'NCHAR'";
      } else {
        $retval="ORATYPE_CHAR";
      }
    } elseif ( $type == 102 ) {
      $retval="ORATYPE_CURSOR";
    } elseif ( $type == 105 || $type == 106 ) {
      $retval="ORATYPE_MLSLABEL";
    } elseif ( $type == 110 || $type == 111 ) {
      $retval="ORATYPE_REF";
    } elseif ( $type == 112 ) {
      if ($this->verbose) {
        $retval="ORATYPE_CLOB // WARNING: Might be 'NCLOB'";
      } else {

        $retval="ORATYPE_CLOB";
      }
    } elseif ( $type == 113 ) {
      $retval="ORATYPE_BLOB";
    } elseif ( $type == 114 ) {
      $retval="ORATYPE_BFILE";
    } elseif ( $type == 115 ) {
      $retval="ORATYPE_CFILE";
      //121 = OBJECT ?
    } elseif ( $type == 122 ) {
      $retval="ORATYPE_TABLE";
    } elseif ( $type == 123 ) {

      $retval="ORATYPE_VARRAY";

      // 178 = TIME ?
      // 179 = TIME WITH TIME ZONE ?
      // 180 = TIMESTAMP ?
      // 181 = TIMESTAMP WITH TIME ZONE ?
      // 182 = INTERVAL YEAR TO MONTH ?
      // 183 = INTERVAL DAY TO SECOND ?
    } else {

      // don't have a clue!
      if ( $this->verbose ) {

        $retval = "FALLBACK // WARNING: Couldn't parse argument type! Not familiar with argument code: " . $type ;
      } else {

        $retval = "FALLBACK";
      }
    }
    return ( $retval );
  }
}
?>
