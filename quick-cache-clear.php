<?php
// Quick cache clear - one line execution
require_once('../../../wp-load.php');
require_once('includes/class-hrw-api-cache.php');
$result = HRW_API_Cache::clear_cache();
echo $result ? '✅ Cache cleared - Alphabetical sorting now active!' : '❌ Cache clear failed';
