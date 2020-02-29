# Application Context Composer plugin

This plugin sets some additional environment variables by patching entry files like _index.php_. In environments where FCGI variables can not be set this plugin patches a list of defined PHP files to bootstrap with the proper `putenv` calls to lete the application "think" it's running in a certain environment.

    "extra": {
      "application-context": {
        "paths": [
          "index.php"
        ],
        "variables": {
          "APPLICATION_CONTEXT": "%env(APPLICATION_CONTEXT)%"
        }
      }
    }

Variables that should be set and thus included in the generated snippet can be configured in _composer.json_ under _extra_. Files that should be patched can also be configured here.

A snippet like the one below is added to the top each file.

    // Prefixed by MaxServ\ComposerApplicationContext\Plugin
    call_user_func(function(){
    if (function_exists('getenv') !== false && function_exists('putenv') !== false){
    if ('%env(APPLICATION_CONTEXT)%' !== '' && stripos('%env(APPLICATION_CONTEXT)%', '%env(') === false) {
      if (getenv('APPLICATION_CONTEXT') === false){putenv('APPLICATION_CONTEXT=%env(APPLICATION_CONTEXT)%');}
      if ($_SERVER['APPLICATION_CONTEXT'] === null){$_SERVER['APPLICATION_CONTEXT'] = '%env(APPLICATION_CONTEXT)%';}
    }
    }
    });
    
At runtime the variable (that is possibly replaced by another script) is evaluated before invoking `getenv` and `putenv`.
