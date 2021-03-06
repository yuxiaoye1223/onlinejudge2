<?php
//require_once("../../judgelib.php");
require_once(dirname(dirname(__FILE__))."/../../../config.php");

global $DB,$CFG;
require_once($CFG->dirroot."/local/onlinejudge/judgelib.php");

class judge_sandbox extends judge_base {
   // var $cases = parent::get_tests;
    var $langs = array(
        //sandbox languages
        'c_warn2err_sandbox'                     =>300,
        'c_sandbox'                              =>301,
        'cpp_warn2err_sandbox'                   =>302,
        'cpp_sandbox'                            =>303,
	);
	
	var $status_arr = array(
            'pending' => 4,
            'nr'      => 0,
            'ac'      => 1,
            'wa'      => 2,
            'pe'      => 3,
            're'      => 12,
            'tle'     => 13,
            'mle'     => 17,
            'ole'     => 16,
            'ce'      => 11,
            'ie'      => 20,
            'rf'      => 5,
            'at'      => 6
        );

    function __construct() {
    }
        
    static function get_languages()
    {
    	global $CFG;

        $langs = array();
        // Get local languages. Linux only
        if ($CFG->ostype != 'WINDOWS') {
            $dir = $CFG->dirroot.'/local/onlinejudge/judge/sandbox/languages/';
            $files = get_directory_list($dir);
            $names = preg_replace('/\.(\w+)/', '_sandbox', $files); // Replace file extension with _sandbox
            foreach ($names as $name) {
                $langs[$name] = get_string('lang'.$name, 'local_onlinejudge');
            }
        }
        return $langs;
        
    }

    function translate_status($status) {
        return $this->status_arr[$status];
    }
    
    function flip_status($statusid) {
        $status_arr_temp = array_flip($this->status_arr);
        return $status_arr_temp[$statusid];
    }
    
    /**
     * 
     * 将数字id转换为编译器可以执行的语言名字，如301转换为c（不可执行名字为c_sandbox）
     * @param integer $id
     */
    function translator($id) {
        $lang_temp = array();
        //将数组的键值调换，存入temp数组
        $lang_temp = array_flip($this->langs);
        //获取翻译后的编译语言，比如‘c_ideone’变成‘c’
        $selected_lang = substr($lang_temp[$id],0,strrpos($lang_temp[$id],'_'));
        
        
        return $selected_lang;        
    }
    
    // Compile submission $task in temp_dir
    // return result class on success, false on error
    function compile($task, $temp_dir) {
        echo "<br>compile begin<br>";
    	global $CFG;
    	//result class
    	$result = new stdClass();
        $result = false;
        //创建存储源代码的.c文件
        $file = 'prog.c';
        //将代码写入文件里
        if($task->source != null) {
            file_put_contents("$temp_dir/$file", $task->source);
            
            //get judge name, such c,c_warn2err, cpp etc..
            $language = substr($task->language, 0, strlen($task->language)-8);
            
            //select the compiler shell.
            //gcc -D_MOODLE_ONLINE_JUDGE_ 	-Wall -static -o $DEST $SOURCE -lm
            $compiler = $CFG->dirroot.'/local/onlinejudge/judge/sandbox/languages/'.$language.'.sh';
            
            if (!is_executable($compiler)) {
            	// for test
                mtrace(get_string('cannotruncompiler', 'local_onlinejudge'));
                
                $result->info_teacher = get_string('cannotruncompiler', 'local_onlinejudge');
                $result->info_student = get_string('cannotruncompiler', 'local_onlinejudge');            
                $result->cpuusage = null;
                $result->memusage = null;
                $result->answer = null;
                $result->status = ONLINEJUDGE2_STATUS_INTERNAL_ERROR;
                $result->judgetime = null;
                $result->error = get_string('cannotruncompiler', 'local_onlinejudge');
                
                break;
            }
        
            //output是一个数组，保存输出信息
            $output = null;
            $return = null;
            // $compiler后面第一个参数为source,第二个参数为dest,2>&1表示将标准错误输出信息定向到标准输出里
            $command = "$compiler $temp_dir/$file $temp_dir/a.out 2>&1";
            
            //change status to judging.
            $result->status = ONLINEJUDGE2_STATUS_JUDGING;
            //return是命令行结果的最后一行
            exec($command, $output, $return);
       
            if ($return) { 
        	    //Compile error
                $result->status = ONLINEJUDGE2_STATUS_INTERNAL_ERROR;
            } 
            else { 
                //$result->status = 'compileok';
                $result->status = ONLINEJUDGE2_STATUS_COMPILATION_OK;
            }

            //strip path info
            //将output里面的$temp_dir.'/'替换为空
            $output = str_replace($temp_dir.'/', '', $output);
            //将output数组结合为字符串，元素中间用\n换行符放置,并转换为html元素
            $error = htmlspecialchars(implode("\n", $output));
            //should judge the user's identity. then get the different info_teacher, info_student.
            //$result->info_teacher = addslashes($error);
            //$result->info_student = addslashes($error);
            
            //in moodle 2.0, use such output function.
            $result->info_teacher = format_text($error);
            $result->info_student = format_text($error); 
                       
            $result->cpuusage = null;
            $result->memusage = null;
            $result->answer = null;
            $result->judgetime = time();
            
            if($result->status == ONLINEJUDGE2_STATUS_COMPILATION_ERROR) {
                $result->error = get_string('status3', 'local_onlinejudge');
            }
            else {
                $result->error = null;
            }
            
            return $result;
        }
        
        //Compile the first file only
        return $result;       
    }
    
    /**
     * @param task is an object, including the user's config data.
     * returns the id of onlinejudge_task table in the database, after compile,
     *         the ide returned reference to the onlinejudge_result table in the database.
     */   
    function judge(& $task) {
    	echo "<br>sandbox judge<br>";
    	global $CFG;
        //temp directory to save the testcase and source.
        if(! isset($CFG->temp_dir)) {
            set_config('temp_dir', $CFG->dirroot.'/temp/onlinejudge/'.$task->userid);
        }
        //result class
        $result = new stdClass();
        $result = $task;
        //if not exist the directory, creat it.
        if (!check_dir_exists($CFG->temp_dir, true, true)) {
            mtrace("Can't mkdir ".$CFG->temp_dir);
            //packing
            $result->cpuusage = null;
            $result->memusage = null;
            $result->answer = null;
            $result->status = ONLINEJUDGE2_STATUS_INTERNAL_ERROR;
            $result->info_teacher = null;
            $result->info_student = null;
            $result->judgetime = null;
            $result->error = get_string('cannotmakedir', 'local_onlinejudge');
            return $result;
        }      
        //get the result class.
        if($result = $this->compile($task, $CFG->temp_dir)) {
            
            if ($result->status === ONLINEJUDGE2_STATUS_COMPILATION_OK && !$task->compileonly) {
                //Run and test
                $result = $this->run_in_sandbox($CFG->temp_dir.'/a.out', $task);	
            } 
            else if ($result->status === ONLINEJUDGE2_STATUS_COMPILATION_ERROR) {
                //$result->grade = 'ce';
                $result->answer = '';
            }	
        } 
             
        return $result;
    }
    
    function run_in_sandbox($exec_file, $task) {
        echo 'run sandbox';
    	global $CFG, $DB;
        //testcase
        $case = new stdClass();          
        $case->input = $task->input.'\n';
        $case->output = $task->output;
    	
        //result class
        $ret = new stdClass();
        $ret = null;
        
        $result = array (
                ONLINEJUDGE2_STATUS_PENDING, 
                ONLINEJUDGE2_STATUS_ACCEPTED,
                ONLINEJUDGE2_STATUS_RESTRICTED_FUNCTIONS, 
                ONLINEJUDGE2_STATUS_MEMORY_LIMIT_EXCEED, 
                ONLINEJUDGE2_STATUS_OUTPUT_LIMIT_EXCEED,
                ONLINEJUDGE2_STATUS_TIME_LIMIT_EXCEED,
                ONLINEJUDGE2_STATUS_RUNTIME_ERROR,
                ONLINEJUDGE2_STATUS_ABNORMAL_TERMINATION,
                ONLINEJUDGE2_STATUS_INTERNAL_ERROR
        );
        //print_r($result);
        
        $sand = $CFG->dirroot . '/local/onlinejudge/judge/sandbox/sand/sand';
        //如果sand不可执行，则返回空的结果对象
        if (!is_executable($sand)){
            mtrace(get_string('cannotrunsand'), 'local_onlinejudge');
            $ret->status = ONLINEJUDGE2_STATUS_INTERNAL_ERROR;
            
            $ret->info_teacher = get_string('cannotrunsand', 'local_onlinejudge');
            $ret->info_student = get_string('cannotrunsand', 'local_onlinejudge');            
            $ret->cpuusage = null;
            $ret->memusage = null;
            $ret->answer = null;
            $ret->judgetime = null;
            $ret->error = get_string('cannotrunsand', 'local_onlinejudge');
            
            return $ret;
        }
        
        $sand .= ' -l cpu='.(($task->cpulimit)*1000).' -l memory='.$task->memlimit.' -l disk=512000 '.$exec_file; 
        //test
        //$sand .= ' -l cpu=1000'.' -l memory=1048576'.' -l disk=512000 '.$exec_file; 
    
        //描述符，包含标准输入，标准输出和标准错误输出
        $descriptorspec = array(
            0 => array('pipe', 'r'),  // stdin is a pipe that the child will read from
            1 => array('file', $exec_file.'.out', 'w'),  // stdout is a file that the child will write to
            2 => array('file', $exec_file.'.err', 'w') // stderr is a file that the child will write to
        );
        $ret->judgetime = time();
        //echo $sand;
        //执行sand命令行，打开文件指针，用于输入输出,返回表示进程的资源
        $proc = proc_open($sand, $descriptorspec, $pipes);
        
        //如果返回的不是资源，即不成功
        if (!is_resource($proc)) {
        	mtrace(get_string('status21', 'local_onlinejudge'));
            $ret->status = ONLINEJUDGE2_STATUS_INTERNAL_ERROR;
            
            $ret->info_teacher = get_string('status21', 'local_onlinejudge');
            $ret->info_student = get_string('status21', 'local_onlinejudge');            
            $ret->cpuusage = null;
            $ret->memusage = null;
            $ret->answer = null;
            $ret->error = get_string('status21', 'local_onlinejudge');
            
            return $ret;
        }
        
        // $pipes now looks like this:
        // 0 => writeable handle connected to child stdin
        // 1 => readable handle connected to child stdout
        // Any error output will be appended to $exec_file.err
        
        //for test
        print_r($pipes);
        
        //将用例输入写入到$pipes[0]指定的文件中。
        fwrite($pipes[0], $case->input);
        fclose($pipes[0]);

        //关闭proc_open打开的进程，并且返回进程的退出代码
        $return_value = proc_close($proc);
        
        //将文件变成字符串，存入结果的输出中
        $ret->answer = file_get_contents($exec_file.'.out');
        
        if(is_null($ret->answer)) {
            mtrace('file_get_contents is null');
        }
        
        if ($return_value == 255) {
            $ret->status = ONLINEJUDGE2_STATUS_INTERNAL_ERROR;
            return $ret;
        } 
        else if ($return_value >= 2) {
            $ret->status = $result[$return_value];
            return $ret;
        } 
        else if($return_value == 0) {
            mtrace('Pending? Why?');
            exit();
        }
        
        //比较结果和用例输出
        $ret->status = $this->diff($case->output, $ret->answer);
              
        $ret->info_teacher = $ret->status;
        $ret->info_student = $ret->status;            
        $ret->cpuusage = null;
        $ret->memusage = null;
        $ret->judgetime = time();
        $ret->error = $ret->status;
        
        return $ret;
    }
}


?>
