<?php

/*
 * @description: het invoerprogramme, import data from
 *               punch card
 * @input: null
 * @output: null
 * @author: Qingwen Chen
 * @TODO: need to set $_SESSION['start_ptr'] and $_SESSION['end_ptr']
*/
function input() {
    reset($_SESSION['punch']);

   do{
        $return_value = execute($_SESSION['mem'][$_SESSION['instr_ptr']]);
    } while ($return_value);
}


/*
 * @description: execute one instruction of input program 
 * @output: null
 * @input: null
 */
function input_one() {
    run();
}


function run_n_instrs($n) 
{
    for($i = 0; $i < $n; $i++)
        if (!run())
            return false;
}

function run_bp($n)
{
    do{
        run();
    } while($_SESSION['instr_ptr'] != $n && !is_end());
}

function is_end()
{
    return !isset($_SESSION['mem'][$_SESSION['instr_ptr']]);
}


/*
 * @description: execute one instruction
 * @input: null
 * @output: null
 * @author: Qingwen Chen
*/
function run() {
    if(!is_end()){
        execute($_SESSION['mem'][$_SESSION['instr_ptr']]);
        return true;
    } else 
        return false;
}


/*
 * @description: execute all remained instructions
 * @input: null
 * @output: null
 * @author: Qingwen Chen
*/
function run_all() {
    do {
        run();
    }while(!is_end());
}



/*
 * @description: excute one instruction, the instruction pointer will update
 *               accordingly, i.e. increases by 1, or in the case where the current
 *               instruction is a jump-instruction, the instruction pointer will
 *               point to the jumped one.
 * @input: an integer which represents a complete instruction ($instr + $addr)
 * @output: null
 * @author: Qingwen Chen
*/
function execute($bininstr) {
    $return_value = 1;
    $instr = ($bininstr & 0x7800) >> 11;
    $addr = $bininstr & 0x7FF;

    $_SESSION['changes']['reg_a'] = 0;
    $_SESSION['changes']['reg_s'] = 0;
    $_SESSION['changes']['memory'] = 0;
    $_SESSION['changes']['output'] = 0;

    switch($instr) {
        case 0:
            $_SESSION['reg_a'] = add($_SESSION['reg_a'], $_SESSION['mem'][$addr]);
            $_SESSION['log'] = $_SESSION['instr_no'].' | '.$_SESSION['instr_ptr']."    $instr/$addr: [A] = [A] + Memory[$addr]\n".$_SESSION['log'];
            $_SESSION['instr_ptr'] = $_SESSION['instr_ptr'] + 1;
            $_SESSION['changes']['reg_a'] = 1;
            break;
        case 1:
            $_SESSION['reg_s'] = $_SESSION['mem'][$addr];
            $_SESSION['log'] = $_SESSION['instr_no'].' | '.$_SESSION['instr_ptr']."    $instr/$addr: [S] = [S] + Memory[$addr]\n".$_SESSION['log'];
            $_SESSION['instr_ptr'] = $_SESSION['instr_ptr'] + 1;
            $_SESSION['changes']['reg_s'] = 1;
            break;
        case 2:
            $_SESSION['mem'][$addr] = $_SESSION['reg_a'];
            $_SESSION['log'] =  $_SESSION['instr_no'].' | '.$_SESSION['instr_ptr']."    $instr/$addr: Memory[$addr] = [A]\n".$_SESSION['log'];
            $_SESSION['instr_ptr'] = $_SESSION['instr_ptr'] + 1;
            $_SESSION['changes']['memory'] = 1;
            break;
        case 3:
            $_SESSION['mem'][$addr] = $_SESSION['reg_s'];
            $_SESSION['reg_a'] = $_SESSION['reg_s'];
            $_SESSION['log'] = $_SESSION['instr_no'].' | '.$_SESSION['instr_ptr']."    $instr/$addr: Memory[$addr] = [S]; [A] = [S];\n".$_SESSION['log'];
            $_SESSION['instr_ptr'] = $_SESSION['instr_ptr'] + 1;
            $_SESSION['changes']['reg_a'] = 1;
            $_SESSION['changes']['memory'] = 1;
            break;
        case 4://TODO: need to deal with multiplication of integers of more than 32 bits.
            $tmp = int30to32($_SESSION['reg_s']) * int30to32($_SESSION['mem'][$addr]) + int30to32($_SESSION['reg_a']);
            $_SESSION['reg_a'] = (int)($tmp / pow(2, 29));
            $_SESSION['reg_s'] = (int)($tmp - $_SESSION['reg_a'] * pow(2,29));
            $_SESSION['reg_a'] = int32to30($_SESSION['reg_a']);
            $_SESSION['reg_s'] = int32to30($_SESSION['reg_s']);
            $_SESSION['log'] = $_SESSION['instr_no'].' | '.$_SESSION['instr_ptr']."    $instr/$addr: P = [S] X Memory[$addr] + [A]; the value of [A] and [S] are specified with P = 2^29 X [A] + [S];\n".$_SESSION['log'];
            $_SESSION['instr_ptr'] = $_SESSION['instr_ptr'] + 1;
            $_SESSION['changes']['reg_a'] = 1;
            $_SESSION['changes']['reg_s'] = 1;
            break;
        case 5:
            $tmp = int30to32($_SESSION['reg_s']) * int30to32($_SESSION['mem'][$addr]);
            if(!is_int($tmp)) {
                echo "DEBUG error: instr 5 overflow\n";
                $_SESSION['reg_a'] = int32to30((int)($tmp - ((int)($tmp / pow(2, 30)))*pow(2, 30)));
            } else {
                $_SESSION['reg_a'] = int32to30($tmp);
            }
            $_SESSION['log'] = $_SESSION['instr_no'].' | '.$_SESSION['instr_ptr']."    $instr/$addr: [A] = [S] X Memory[$addr];\n".$_SESSION['log'];
            $_SESSION['instr_ptr'] = $_SESSION['instr_ptr'] + 1;
            $_SESSION['changes']['reg_a'] = 1;
            break;
        case 6:
            $_SESSION['reg_a'] = ($_SESSION['reg_a'] << $addr) & 0x3FFFFFFF;
            $_SESSION['log'] = $_SESSION['instr_no'].' | '.$_SESSION['instr_ptr']."    $instr/$addr: [A] = [A] 2^$addr;\n".$_SESSION['log'];
            $_SESSION['instr_ptr'] = $_SESSION['instr_ptr'] + 1;
            $_SESSION['changes']['reg_a'] = 1;
            break;
        case 7:
            //jumps to instruction n if (A) >= 0;
            if(int30to32($_SESSION['reg_a']) >= 0) {
                $_SESSION['log'] = $_SESSION['instr_no'].' | '.$_SESSION['instr_ptr']."    $instr/$addr: Jumps to instruction $addr.\n".$_SESSION['log'];
                $_SESSION['instr_ptr'] = $addr;
            } else {
                $_SESSION['log'] = $_SESSION['instr_no'].' | '.$_SESSION['instr_ptr']."    $instr/$addr: Continue with instruction ".($_SESSION['instr_ptr'] +1).".\n".$_SESSION['log'];
                $_SESSION['instr_ptr'] = $_SESSION['instr_ptr'] + 1;
            }
            break;
        case 8:
            $_SESSION['reg_a'] = sub(int30to32($_SESSION['reg_a']), int30to32($_SESSION['mem'][$addr]));
            $_SESSION['log'] = $_SESSION['instr_no'].' | '.$_SESSION['instr_ptr']."    $instr/$addr: [A] = [A] - Memory[$addr];\n".$_SESSION['log'];
            $_SESSION['instr_ptr'] = $_SESSION['instr_ptr'] + 1;
            $_SESSION['changes']['reg_a'] = 1;
            break;
        case 9:
        #TODO: communicates with the typewriter or punch and puts |(A)| in
        #register A.
            $_SESSION['reg_a'] = int32to30(abs(int30to32($_SESSION['reg_a'])));
            $return_value = instr9($addr);
            $_SESSION['changes']['reg_a'] = 1;
            if($addr != 49)
                $_SESSION['log'] = $_SESSION['instr_no'].' | '.$_SESSION['instr_ptr']."    $instr/$addr: output to the typewriter.\n".$_SESSION['log'];
            $_SESSION['instr_ptr'] = $_SESSION['instr_ptr'] + 1;
            break;
        case 10:
            $_SESSION['mem'][$addr] = $_SESSION['reg_a'];
            $_SESSION['reg_a'] = 0;
            $_SESSION['log'] = $_SESSION['instr_no'].' | '.$_SESSION['instr_ptr']."    $instr/$addr: Memory[$addr] = [A]; [A] = 0;\n".$_SESSION['log'];
            $_SESSION['instr_ptr'] = $_SESSION['instr_ptr'] + 1;
            $_SESSION['changes']['reg_a'] = 1;
            $_SESSION['changes']['memory'] = 1;
            break;
        case 11 :
            $_SESSION['mem'][$addr] = $_SESSION['reg_s'];
            $_SESSION['reg_a'] = 0;
            $_SESSION['log'] = $_SESSION['instr_no'].' | '.$_SESSION['instr_ptr']."    $instr/$addr: Memory[$addr] = [S]; [A] = 0;\n".$_SESSION['log'];
            $_SESSION['instr_ptr'] = $_SESSION['instr_ptr'] + 1;
            $_SESSION['changes']['reg_a'] = 1;
            $_SESSION['changes']['memory'] = 1;
            break;
        case 12:
            $tmp = int30to32($_SESSION['reg_a']) * pow(2, 29) + int30to32($_SESSION['reg_s'] & 0x1FFFFFFF);
            $_SESSION['reg_s'] = (int)($tmp / int30to32($_SESSION['mem'][$addr]));
            $_SESSION['reg_a'] = int32to30((int)($tmp - $_SESSION['reg_s'] * int30to32($_SESSION['mem'][$addr])));
            $_SESSION['reg_s'] = int32to30($_SESSION['reg_s']);
            $_SESSION['log'] = $_SESSION['instr_no'].' | '.$_SESSION['instr_ptr']."    $instr/$addr: [A] = (2^29 X [A] + [S]) % Memory[$addr]; [S] = (2^29 X [A] + [S]) / Memory[$addr];\n".$_SESSION['log'];
            $_SESSION['instr_ptr'] = $_SESSION['instr_ptr'] + 1;
            $_SESSION['changes']['reg_a'] = 1;
            $_SESSION['changes']['reg_s'] = 1;
            break;
        case 13:
            $_SESSION['reg_s'] = int32to30((int)(int30to32($_SESSION['reg_a']) / int30to32($_SESSION['mem'][$addr])));
            $_SESSION['reg_a'] = 0;
            $_SESSION['log'] = $_SESSION['instr_no'].' | '.$_SESSION['instr_ptr']."    $instr/$addr: [S] = [A] / Memory[$addr];\n".$_SESSION['log'];
            $_SESSION['instr_ptr'] = $_SESSION['instr_ptr'] + 1;
            $_SESSION['changes']['reg_a'] = 1; 
            $_SESSION['changes']['reg_s'] = 1;
            break;
        case 14:
            $_SESSION['reg_a'] = int32to30((int)(int30to32($_SESSION['reg_a']) * pow(2, -$addr)));
            $_SESSION['log'] = $_SESSION['instr_no'].' | '.$_SESSION['instr_ptr']."    $instr/$addr: [A] = [A] X 2^-29;\n".$_SESSION['log'];
            $_SESSION['instr_ptr'] = $_SESSION['instr_ptr'] + 1;
            $_SESSION['changes']['reg_a'] = 1;
            break;
        case 15:
            //jumps to instruction n if (A) < 0.
            if(int30to32($_SESSION['reg_a']) < 0) {
                $_SESSION['log'] = $_SESSION['instr_no'].' | '.$_SESSION['instr_ptr']."    $instr/$addr: Jumps to instruction $addr.\n".$_SESSION['log'];
                $_SESSION['instr_ptr'] = $addr;
            } else {
                $_SESSION['log'] = $_SESSION['instr_no'].' | '.$_SESSION['instr_ptr']."    $instr/$addr: Continue with instruction ".($_SESSION['instr_ptr']+1).".\n".$_SESSION['log'];
                $_SESSION['instr_ptr'] = $_SESSION['instr_ptr'] + 1;
            }

            break;
        default :
            $_SESSION['log'] = $_SESSION['instr_no'].' | '.$_SESSION['instr_ptr']."    $instr/$addr: ERROR: Unknown instructions!\n".$_SESSION['log'];
            break;
    }

    $_SESSION['instr_no']++;
    return $return_value;
}



function int2float($n)
{
    for($i = 0; $i < 30; $i++)
        $d[$i] = ($n >> (29 - $i)) & 0x1;

    for($i = 29; $i > 0; $i--){
        $result += ($d[$i] - $d[0]) * pow(2, -$i);
    }

    return $result;
}


function formatoutput($reg_a, $bef, $aft)
{
    $str = "%".$bef.".".$aft."f";
    return sprintf($str, $reg_a * pow(10, $bef));
}

/*
 * @description: Execute instruction 9.
 * @input: n
 * @output: record the output in $_SESSION['output']
 * @author: Qingwen Chen   
*/
function instr9($n) {
    $return_value = 1; 
    $_SESSION['changes']['output'] = 1;
    $reg_a = int2float($_SESSION['reg_a']);
    switch($n) {
        case 0:
            $_SESSION['output'] = $_SESSION['output'].$_SESSION['reg_a'];
            break;
        case 1:
            $i = 10000000;
            while($i != 0 && floor($_SESSION['reg_a']/$i) != 0){
                $prefix .= '0';
                $i = $i/10;
            }
            $_SESSION['output'] = $_SESSION['output'].$prefix.$_SESSION['reg_a'];
        case 5:
            $_SESSION['output'] = $_SESSION['output'].'0';
            break;
        case 3:
            $_SESSION['output'] = $_SESSION['output'].'1';
            break;
        case 14:
            $_SESSION['output'] = $_SESSION['output'].'2';
            break;
        case 57:
            $_SESSION['output'] = $_SESSION['output'].'3';
            break;
        case 10:
            $_SESSION['output'] = $_SESSION['output'].'4';
            break;
        case 15:
            $_SESSION['output'] = $_SESSION['output'].'5';
            break;
        case 58:
            $_SESSION['output'] = $_SESSION['output'].'6';
            break;
        case 25:
            $_SESSION['output'] = $_SESSION['output'].'7';
            break;
        case 59:
            $_SESSION['output'] = $_SESSION['output'].'8';
            break;
        case 62:
            $_SESSION['output'] = $_SESSION['output'].'9';
            break;
        case 16:
            $_SESSION['output'] = $_SESSION['output'].'+';
            break;
        case 42:
            $_SESSION['output'] = $_SESSION['output'].'-';
            break;
        case 6:
            $_SESSION['output'] = $_SESSION['output'].'.';
            break;
        case 61:
            $_SESSION['output'] = $_SESSION['output'].'\n';
            break;
        case 60:        //a tab with tabwidth=4
            $_SESSION['output'] = $_SESSION['output'].'    ';
            break;
        case 63:        //a whitespace
            $_SESSION['output'] = $_SESSION['output'].' ';
            break;

        /*print the value in register A*/
        case 2:
            $_SESSION['output'] = $_SESSION['output'].sprintf(".%8d", (int)($reg_a * 100000000));
            break;
        case 4:
            $_SESSION['output'] = $_SESSION['output'].sprintf("%0.7f", (int) ($reg_a * 10000000));
            break;
        case 7:
            $_SESSION['output'] = $_SESSION['output'].sprintf("%0.6f", (int)($reg_a * 1000000));
            break;
        case 8:
            $_SESSION['output'] = $_SESSION['output'].sprintf("%0.5f", (int)($reg_a * 100000));
            break;
        case 9:
            $_SESSION['output'] = $_SESSION['output'].sprintf("%0.4f", (int)($reg_a * 10000));
            break;
        case 11:
            $_SESSION['output'] = $_SESSION['output'].sprintf("%0.3f", (int)($reg_a*1000));
            break;
        case 12:
            $_SESSION['output'] = $_SESSION['output'].sprintf("%0.2f", (int)($reg_a*100));
            break;

        case 13:
            $_SESSION['output'] = $_SESSION['output'].formatoutput($reg_a, 1, 7);
            break;
        case 17:
            $_SESSION['output'] = $_SESSION['output'].formatoutput($reg_a, 1, 6);
            break;
        case 18:
            $_SESSION['output'] = $_SESSION['output'].formatoutput($reg_a, 1, 5);
            break;
        case 19:
            $_SESSION['output'] = $_SESSION['output'].formatoutput($reg_a, 1, 4);
            break;
        case 20:
            $_SESSION['output'] = $_SESSION['output'].formatoutput($reg_a, 1, 3);
            break;
        case 21:
            $_SESSION['output'] = $_SESSION['output'].formatoutput($reg_a, 1, 2);
            break;
        case 22:
            $_SESSION['output'] = $_SESSION['output'].formatoutput($reg_a, 1, 1);
            break;

        case 23:
            $_SESSION['output'] = $_SESSION['output'].formatoutput($reg_a, 2, 6);
            break;
        case 24:
            $_SESSION['output'] = $_SESSION['output'].formatoutput($reg_a, 2, 5);
            break;
        case 26:
            $_SESSION['output'] = $_SESSION['output'].formatoutput($reg_a, 2, 4);
            break;
        case 27:
            $_SESSION['output'] = $_SESSION['output'].formatoutput($reg_a, 2, 3);
            break;
        case 28:
            $_SESSION['output'] = $_SESSION['output'].formatoutput($reg_a, 2, 2);
            break;
        case 29:
            $_SESSION['output'] = $_SESSION['output'].formatoutput($reg_a, 2, 1);
            break;
        case 30:
            $_SESSION['output'] = $_SESSION['output'].formatoutput($reg_a, 2, 0);
            break;

        case 31:
            $_SESSION['output'] = $_SESSION['output'].formatoutput($reg_a, 3, 5);
            break;
        case 32:
            $_SESSION['output'] = $_SESSION['output'].formatoutput($reg_a, 3, 4);
            break;
        case 33:
            $_SESSION['output'] = $_SESSION['output'].formatoutput($reg_a, 3, 3);
            break;
        case 34:
            $_SESSION['output'] = $_SESSION['output'].formatoutput($reg_a, 3, 2);
            break;
        case 35:
            $_SESSION['output'] = $_SESSION['output'].formatoutput($reg_a, 3, 1);
            break;
        case 36:
            $_SESSION['output'] = $_SESSION['output'].formatoutput($reg_a, 3, 0);
            break;

        case 37:
            $_SESSION['output'] = $_SESSION['output'].formatoutput($reg_a, 4, 4);
            break;
        case 38:
            $_SESSION['output'] = $_SESSION['output'].formatoutput($reg_a, 4, 3);
            break;
        case 39:
            $_SESSION['output'] = $_SESSION['output'].formatoutput($reg_a, 4, 2);
            break;
        case 40:
            $_SESSION['output'] = $_SESSION['output'].formatoutput($reg_a, 4, 1);
            break;
        case 41:
            $_SESSION['output'] = $_SESSION['output'].formatoutput($reg_a, 4, 0);
            break;

        case 43:
            $_SESSION['output'] = $_SESSION['output'].formatoutput($reg_a, 5, 3);
            break;
        case 44:
            $_SESSION['output'] = $_SESSION['output'].formatoutput($reg_a, 5, 2);
            break;
        case 45:
            $_SESSION['output'] = $_SESSION['output'].formatoutput($reg_a, 5, 1);
            break;
        case 46:
            $_SESSION['output'] = $_SESSION['output'].formatoutput($reg_a, 5, 0);
            break;


        case 49:    //read a line from the punch card
            //list($key, $value) = each($_SESSION['punch']);
            if($_SESSION['punch_no'] < count($_SESSION['punch'])) {
                $_SESSION['reg_a'] = $_SESSION['punch'][$_SESSION['punch_no']];
                $_SESSION['changes']['reg_a'] = 1;
                $_SESSION['log'] = $_SESSION['instr_no'].' | '.$_SESSION['instr_ptr']."    9/49: read line ".$_SESSION['punch_no']." from the punch tape.\n".$_SESSION['log'];
            } else {
                $return_value = 0;
                $_SESSION['log'] = $_SESSION['instr_no'].' | '.$_SESSION['instr_ptr']."    9/49: supposed to read a line from the punch tape, but reaches the end.\n".$_SESSION['log'];
            }
            $_SESSION['punch_no']++;
            $_SESSION['changes']['output'] = 0;
            break;

        case 50:
            $_SESSION['output'] = $_SESSION['output'].formatoutput($reg_a, 6, 2);
            break;
        case 51:
            $_SESSION['output'] = $_SESSION['output'].formatoutput($reg_a, 6, 1);
            break;
        case 52:
            $_SESSION['output'] = $_SESSION['output'].formatoutput($reg_a, 6, 0);
            break;

        case 53:
            $_SESSION['output'] = $_SESSION['output'].formatoutput($reg_a, 7, 1);
            break;
        case 54:
            $_SESSION['output'] = $_SESSION['output'].formatoutput($reg_a, 7, 0);
            break;
    }
    return $return_value;
}


/*
 * @description: Readable instructions are in the format of (instr, addr). This
 *               function convert the readable format into binary one.
 * @input: $instr, an integer between 0 and 15
 *         $addr, address of data, an integer between 0 and 0x7ff (11 bits).
 * @output: integrated instruction
 * @author: Qingwen Chen
*/
function instr2bin($instr, $addr) {
    return ($instr<<11)+$addr;
}



/*
 * @description: convert a complete instruction (integer) into the format of $instr/$addr
 * @input: the complete instruction (an integer)
 * @output: return an array with two elements, $instr and $addr
 * @author: Qingwen Chen
 */
function mem_format($bininstr) {
    return array(($bininstr & 0x7800) >> 11, $bininstr & 0x7FF);
}


/*
 * @description: addition of two 30-bits integers
 * @input: $a and $b which are the 1st and 2nd integers
 * @output: $a + $b, a 30-bits integer
 * @author: Qingwen Chen
 */
function add($a, $b)
{    
    if(!is_int($a) || !is_int($b)){
        echo "Debug error: type of arguments is not integer.\n";
        $a = (int) $a;
        $b = (int) $b;
    }

    return int32to30(int30to32($a) + int30to32($b));
}


/*
 * @description: subtraction of two 30-bits integers
 * @input: $a and $b which are the 1st and 2nd 30-bits integers
 * @output: $a - $b, which is a 30-bits integer
 * @author: Qingwen Chen 
 */
function sub($a, $b)
{    
    if(!is_int($a) || !is_int($b)){
        echo "Debug error: type of arguments is not integer.\n";
        $a = (int) $a;
        $b = (int) $b;
    }

    return int32to30(int30to32($a) - int30to32($b));
}


/*
 * @description: multiplication of two 30-bits integers
 * @input: $a and $b which are the 1st and 2nd 30-bits integers
 * @output: $a * $b, which is a 30-bits integer
 * @author: Qingwen Chen 
 */
function mult($a, $b)
{    
    if(!is_int($a) || !is_int($b)){
        echo "Debug error: type of arguments is not integer.\n";
        $a = (int) $a;
        $b = (int) $b;
    }

    return int32to30(int30to32($a) * int30to32($b));
}


/*
 * @description: division of two 30-bits integers
 * @input: $a and $b which are the 1st and 2nd 30-bits integers
 * @output: $a / $b, which is a 30-bits integer
 * @author: Qingwen Chen 
 */
function div($a, $b)
{    
    if(!is_int($a) || !is_int($b)){
        echo "Debug error: type of arguments is not integer.\n";
        $a = (int) $a;
        $b = (int) $b;
    }

    return int32to30(floor(int30to32($a)/int30to32($b)));
}



/*
 * @description: An integer on ARRA is 30 bits which is different 
 *               from the case on other machines, nomrally 32 bits.
 *               This function converts 30-bits integer into 32-bits
 *               integer by coping the highest bit (leftmost bit or 29th
 *               bit) in 30-bits integer to 30th and 31th bits in 32-bits
 *               integer.
 * @input: 30-bits integer in binary format of octal format.
 * @output: 32-bits with the same value as 30-bits
 * @author: Qingwen Chen
 */
function int30to32($a)
{ 
    if(PHP_INT_SIZE == 4){ //if integer size is 32 bits
        if(!is_int($a)){
            echo "Debug error: argument type is not integer.\n";
            $a = (int) $a;
        }

        if($a & 0x20000000) {
            return ($a | 0xC0000000) + 1; //A negative number is represented as
            //1's complement in PHP.
        } else {
            return $a;
        }
    } elseif(PHP_INT_SIZE == 8) {  //if integer size is 64 bits
        if(!is_int($a)){
            echo "Debug error: argument type is not integer.\n";
            $a = (int) $a;
        }

        if($a & 0x20000000) {
            return ($a | 0xFFFFFFFFC0000000) + 1; //A negative number is represented as
            //1's complement in PHP.
        } else {
            return $a;
        }
    } else {
        echo "Fatal error: unknown integer size!!!\n";
    }
}

/*
 * @description: An integer on ARRA is 30 bits which is different 
 *               from the case on other machines, nomrally 32 bits.
 *               This function converts 32-bits integer into 30-bits
 *               integer by truncating the two highest bits of a
 *               32-bits integer, e.g. 31st and 30th bits.
 * @input: 32-bits integer
 * @output: 30-bits integer
 * @author: Qingwen Chen
 */
function int32to30($a)
{
    if(!is_int($a)){
        echo "Debug error: argument type is not integer.\n";
        $a = (int) $a;
    }
    if($a & 0x20000000)
        $a = $a - 1; //to eliminate the effect of 1's complement for
                     //negative integers
    return ($a & 0x3FFFFFFF);
}


/*
 * @description:load 'het invoerprogramma' to the memory
 * @input: null
 * @output: null
 * @author: Qingwen Chen
 */ 
function init() {
    $_SESSION['start_ptr'] = 0;
    $_SESSION['instr_ptr'] = 0;
    $_SESSION['reg_a'] = 0;
    $_SESSION['reg_s'] = 0;
    $_SESSION['instr_no'] = 0;
    $_SESSION['punch_no'] = 0;
    $_SESSION['log'] = "";
  
    $_SESSION['mem'][0]  = 017;
    $_SESSION['mem'][1]  = 0200050103;
    $_SESSION['mem'][2]  = 04200200020;
    $_SESSION['mem'][3]  = 07777750105;
    $_SESSION['mem'][4]  = 04200450106;
    $_SESSION['mem'][5]  = 07777750100;
    $_SESSION['mem'][6]  = 07777744061;
    $_SESSION['mem'][7]  = 04201050101; 
    $_SESSION['mem'][8]  = 04202044061;
    $_SESSION['mem'][9]  = 04204040000;
    $_SESSION['mem'][10] = 07777774022;
    $_SESSION['mem'][11] = 04210004060;
    $_SESSION['mem'][12] = 04220020100; 
    $_SESSION['mem'][13] = 04100254100;
    $_SESSION['mem'][14] = 07777734010;
    $_SESSION['mem'][15] = 07777734025;
    $_SESSION['mem'][16] = 07777734053;
    $_SESSION['mem'][17] = 04100400124;
    $_SESSION['mem'][18] = 04101000021;
    $_SESSION['mem'][19] = 04102050102;
    $_SESSION['mem'][20] = 04104034102;
    $_SESSION['mem'][21] = 04110000100;
    $_SESSION['mem'][22] = 04120050100;
    $_SESSION['mem'][23] = 04040200101;
    $_SESSION['mem'][24] = 04040440000;
    $_SESSION['mem'][25] = 07777774035;
    $_SESSION['mem'][26] = 04041030013;
    $_SESSION['mem'][27] = 04042000100;
    $_SESSION['mem'][28] = 04044034104;
    $_SESSION['mem'][29] = 04050000020;
    $_SESSION['mem'][30] = 04060050101;
    $_SESSION['mem'][31] = 04020234101;
    $_SESSION['mem'][32] = 04020440100;
    $_SESSION['mem'][33] = 04021050100;
    $_SESSION['mem'][34] = 04022000100;
    $_SESSION['mem'][35] = 04024064061;
    $_SESSION['mem'][36] = 04030054100;
    $_SESSION['mem'][37] = 04010240100;
    $_SESSION['mem'][38] = 04010434104;
    $_SESSION['mem'][39] = 04011074104;
    $_SESSION['mem'][40] = 04012000100;
    $_SESSION['mem'][41] = 04014000062;
    $_SESSION['mem'][42] = 07777774055;
    $_SESSION['mem'][43] = 04004200104;
    $_SESSION['mem'][44] = 04004400057;
    $_SESSION['mem'][45] = 04005050104;
    $_SESSION['mem'][46] = 04006034005;
    $_SESSION['mem'][47] = 1;
    $_SESSION['mem'][48] = 10;
    $_SESSION['mem'][49] = 0575360400;
    $_SESSION['mem'][50] = 04002250000;
    $_SESSION['mem'][51] = 04002400000;
    $_SESSION['mem'][52] = 04003000000;
    $_SESSION['mem'][53] = 04001200000;
    $_SESSION['mem'][54] = 04001400000;
    $_SESSION['mem'][55] = 040000;
    $_SESSION['mem'][56] = 03777777777;
    $_SESSION['mem'][57] = 07765605114;
    $_SESSION['mem'][58] = 07776763533;
    $_SESSION['mem'][59] = 07777713443;
    $_SESSION['mem'][60] = 07777772573;
    $_SESSION['mem'][61] = 07777777364;
    $_SESSION['mem'][62] = 07777777744;
    $_SESSION['mem'][63] = 07777777774;
}

?>
