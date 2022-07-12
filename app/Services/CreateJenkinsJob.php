<?php
namespace App\Services;

use GuzzleHttp\Client;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Artisan;

class CreateJenkinsJob {

    public static function handleData($data){
        $user = Auth::guard('api')->user();
        $name = User::query()->where('id', $user['id'])->value('name');
        $success_to = self::emailMembers($data['to_members'], 'to');
        $success_cc = self::emailMembers($data['cc_members'], 'cc');
        $success_email = $success_to.$success_cc;
        foreach($data['flow'] as $item){
            if(strpos($item, '.git')!==false){
                $git_matched = preg_match('/[^\/]+[?=.git]$/', $item, $git_matches);
                if ($git_matched){
                    $job_name = $git_matches[0];
                    $job_name = str_replace('.git', '', $job_name);
                }
            }else{
                $svn_matched = preg_match('/[^\/]+$/', $item, $svn_matches);
                if ($svn_matched){
                    $job_name = $svn_matches[0];
                }
            }
            foreach($data['tool'] as $tool){
                switch($tool){
                    case "pclint":
                        $code = self::pclintXml($job_name, $data['description'], $item, $data['subject'], $success_email, $name);
                    break;
                    case "tscancode":
                        $code = self::tscancodeXml($job_name, $data['description'], $item, $data['subject'], $success_email, $name);
                    break;
                    case "eslint":
                        $code = self::eslintXml($job_name, $data['description'], $item, $data['subject'], $success_email, $name);
                    break;
                    case "cloc":
                        $code = self::clocXml($job_name, $data['description'], $item, $data['subject'], $success_email, $name);
                    break;
                    case "diffcount":
                        $code = self::diffcountXml($job_name, $data['description'], $item, $data['subject'], $success_email, $name);
                    break;

                }
            }
        }
    }

    private static function emailMembers($members, $type){
        $result = '';
        foreach($members as $member){
            if($member['value']){
                $name = User::query()->where('id', $member['value'])->value('name');
                if($type === 'to'){
                    $result .= $name.',';
                }else{
                    $result .= 'cc:'.$name.',';
                }
            }
        }
        return $result;
    }

    private static function pclintXml($job_name, $description, $code_url, $subject, $success_email, $failure_email){
        $config_xml = <<<'config_xml'
<?xml version='1.1' encoding='UTF-8'?>
<project>
    <actions/>
    <description> %s </description>
    <keepDependencies>false</keepDependencies>
    <properties>
        <jenkins.model.BuildDiscarderProperty>
            <strategy class="hudson.tasks.LogRotator">
                <daysToKeep>-1</daysToKeep>
                <numToKeep>10</numToKeep>
                <artifactDaysToKeep>-1</artifactDaysToKeep>
                <artifactNumToKeep>-1</artifactNumToKeep>
            </strategy>
        </jenkins.model.BuildDiscarderProperty>
    </properties>
    <scm class="hudson.scm.SubversionSCM" plugin="subversion@2.13.1">
        <locations>
            <hudson.scm.SubversionSCM_-ModuleLocation>
                <remote>%s</remote>
                <credentialsId>40c952fe-70ec-4bf5-9651-930d6585a837</credentialsId>
                <local>.</local>
                <depthOption>infinity</depthOption>
                <ignoreExternalsOption>false</ignoreExternalsOption>
                <cancelProcessOnExternalsFail>false</cancelProcessOnExternalsFail>
            </hudson.scm.SubversionSCM_-ModuleLocation>
            <hudson.scm.SubversionSCM_-ModuleLocation>
                <remote>http://172.16.6.128/svn/yfzlb/branches/c0-ci</remote>
                <credentialsId>40c952fe-70ec-4bf5-9651-930d6585a837</credentialsId>
                <local>./c0-ci</local>
                <depthOption>infinity</depthOption>
                <ignoreExternalsOption>false</ignoreExternalsOption>
                <cancelProcessOnExternalsFail>false</cancelProcessOnExternalsFail>
            </hudson.scm.SubversionSCM_-ModuleLocation>
        </locations>
        <excludedRegions></excludedRegions>
        <includedRegions></includedRegions>
        <excludedUsers></excludedUsers>
        <excludedRevprop></excludedRevprop>
        <excludedCommitMessages></excludedCommitMessages>
        <workspaceUpdater class="hudson.scm.subversion.UpdateUpdater"/>
        <ignoreDirPropChanges>false</ignoreDirPropChanges>
        <filterChangelog>false</filterChangelog>
        <quietOperation>true</quietOperation>
    </scm>
    <assignedNode>Pclint</assignedNode>
    <canRoam>false</canRoam>
    <disabled>false</disabled>
    <blockBuildWhenDownstreamBuilding>false</blockBuildWhenDownstreamBuilding>
    <blockBuildWhenUpstreamBuilding>false</blockBuildWhenUpstreamBuilding>
    <triggers>
        <hudson.triggers.SCMTrigger>
            <spec>H  20 * * 1-5</spec>
            <ignorePostCommitHooks>false</ignorePostCommitHooks>
        </hudson.triggers.SCMTrigger>
    </triggers>
    <concurrentBuild>false</concurrentBuild>
    <customWorkspace>/home/jenkins/</customWorkspace>
    <builders>
        <hudson.tasks.Shell>
            <command>chmod -R a+x c0-ci/
python2 c0-ci/ci/script/PROJECT/pclint/pclint_startup.py File</command>
            <configuredLocalRules/>
        </hudson.tasks.Shell>
    </builders>
    <publishers>
        <hudson.tasks.ArtifactArchiver>
            <artifacts>c0-ci/ci/output/formated/</artifacts>
            <allowEmptyArchive>false</allowEmptyArchive>
            <onlyIfSuccessful>false</onlyIfSuccessful>
            <fingerprint>false</fingerprint>
            <defaultExcludes>true</defaultExcludes>
            <caseSensitive>true</caseSensitive>
            <followSymlinks>false</followSymlinks>
        </hudson.tasks.ArtifactArchiver>
        <hudson.plugins.summary__report.ACIPluginPublisher plugin="summary_report@1.15">
            <name>c0-ci/ci/output/formated/publish/10_pclint.xml</name>
            <shownOnProjectPage>true</shownOnProjectPage>
        </hudson.plugins.summary__report.ACIPluginPublisher>
        <hudson.plugins.emailext.ExtendedEmailPublisher plugin="email-ext@2.77">
            <recipientList></recipientList>
            <configuredTriggers>
                <hudson.plugins.emailext.plugins.trigger.SuccessTrigger>
                    <email>
                        <recipientList>%s</recipientList>
                        <subject>【PCLINT检测】%s -- 检查结果#${BUILD_NUMBER} --${BUILD_STATUS}</subject>
                        <body>Hi All,   
&lt;/br&gt;
&lt;html&gt;  
&lt;body leftmargin=&quot;8&quot; marginwidth=&quot;0&quot; topmargin=&quot;8&quot; marginheight=&quot;8&quot; offset=&quot;0&quot;&gt;  
    &lt;tr&gt;  
    &lt;td&gt;&lt;br/&gt;&lt;b&gt;&lt;font color=&quot;#0B610B&quot;&gt;构建结果:${BUILD_STATUS}&lt;/font&gt;&lt;/b&gt;&lt;/td&gt;  
    &lt;/tr&gt;  
    &lt;tr&gt;  
    &lt;td&gt;&lt;br/&gt;&lt;b&gt;&lt;font color=&quot;#0B610B&quot;&gt;构建信息:&lt;/font&gt;&lt;/b&gt;&lt;/td&gt;   
    &lt;/tr&gt;  
    &lt;tr&gt;  
    &lt;td&gt;  
        &lt;ul&gt;  
                &lt;li&gt;项目名称 - ${PROJECT_NAME}&lt;/li&gt;  
                &lt;li&gt;Build URL - &lt;a href=&quot;${BUILD_URL}&quot;&gt;${BUILD_URL}&lt;/a&gt;&lt;/li&gt;
                &lt;li&gt;构建日志 - &lt;a href=&quot;${BUILD_URL}console&quot;&gt;${BUILD_URL}console&lt;/a&gt;&lt;/li&gt;
        &lt;/ul&gt;  
    &lt;/td&gt;  
    &lt;/tr&gt;  
    &lt;tr&gt;  
    &lt;td&gt;&lt;b&gt;&lt;font color=&quot;#0B610B&quot;&gt;本次构建报告结果展示:&lt;/font&gt;&lt;/b&gt;&lt;/td&gt;  
        ${FILE,path=&quot;./c0-ci/ci/output/formated/email/report.html&quot;}
    &lt;/tr&gt;    
&lt;/body&gt;  
&lt;/html&gt;</body>
                        <recipientProviders>
                            <hudson.plugins.emailext.plugins.recipients.ListRecipientProvider/>
                        </recipientProviders>
                        <attachmentsPattern></attachmentsPattern>
                        <attachBuildLog>false</attachBuildLog>
                        <compressBuildLog>false</compressBuildLog>
                        <replyTo>$PROJECT_DEFAULT_REPLYTO</replyTo>
                        <contentType>text/html</contentType>
                    </email>
                </hudson.plugins.emailext.plugins.trigger.SuccessTrigger>
                <hudson.plugins.emailext.plugins.trigger.FailureTrigger>
                    <email>
                        <recipientList>%s</recipientList>
                        <subject>【PCLINT检测】%s -- 检查结果#${BUILD_NUMBER} --${BUILD_STATUS}</subject>
                        <body>$PROJECT_DEFAULT_CONTENT</body>
                        <recipientProviders>
                            <hudson.plugins.emailext.plugins.recipients.ListRecipientProvider/>
                        </recipientProviders>
                        <attachmentsPattern></attachmentsPattern>
                        <attachBuildLog>false</attachBuildLog>
                        <compressBuildLog>false</compressBuildLog>
                        <replyTo>$PROJECT_DEFAULT_REPLYTO</replyTo>
                        <contentType>text/html</contentType>
                    </email>
                </hudson.plugins.emailext.plugins.trigger.FailureTrigger>
            </configuredTriggers>
            <contentType>text/html</contentType>
            <defaultSubject>$DEFAULT_SUBJECT</defaultSubject>
            <defaultContent>$DEFAULT_CONTENT</defaultContent>
            <attachmentsPattern></attachmentsPattern>
            <presendScript>$DEFAULT_PRESEND_SCRIPT</presendScript>
            <postsendScript>$DEFAULT_POSTSEND_SCRIPT</postsendScript>
            <attachBuildLog>false</attachBuildLog>
            <compressBuildLog>false</compressBuildLog>
            <replyTo>$DEFAULT_REPLYTO</replyTo>
            <from>yfzlb@kedacom.com</from>
            <saveOutput>false</saveOutput>
            <disabled>false</disabled>
        </hudson.plugins.emailext.ExtendedEmailPublisher>
    </publishers>
    <buildWrappers/>
</project>
config_xml;

        $config = sprintf($config_xml, $description, $code_url, $success_email, $subject, $failure_email, $subject);
        $base_uri = 'http://172.16.1.171:8080/view/PC-LInt/createItem?name='.$job_name.'_pclint';
        $authorization = 'Basic '.config('jenkins.jenkins_one_one_seven_one');
        $client = new \GuzzleHttp\Client([
            'base_uri' => $base_uri,
            'headers' => [
                'Content-Type' => 'application/xml; charset=UTF-8',
                'Authorization' => $authorization, 
            ],
            'body' => $config,
        ]);
        $response = $client->request('POST');
        $res = $response->getStatusCode();
        if ($res === 200){
            $build_base_uri = 'http://172.16.1.171:8080/job/'.$job_name.'_pclint'.'/build';
            $build = new \GuzzleHttp\Client([
                'base_uri' => $build_base_uri,
                'headers' => [
                    'Content-Type' => 'application/xml; charset=UTF-8',
                    'Authorization' => $authorization, 
                ],
            ]);
            $build->request('POST');
        }
        return $res;
    }

    private static function tscancodeXml($job_name, $description, $code_url, $subject, $success_email, $failure_email){
        $config_xml = <<<'config_xml'
<?xml version='1.1' encoding='UTF-8'?>
<project>
    <actions/>
    <description> %s </description>
    <keepDependencies>false</keepDependencies>
    <properties>
        <jenkins.model.BuildDiscarderProperty>
            <strategy class="hudson.tasks.LogRotator">
                <daysToKeep>-1</daysToKeep>
                <numToKeep>10</numToKeep>
                <artifactDaysToKeep>-1</artifactDaysToKeep>
                <artifactNumToKeep>-1</artifactNumToKeep>
            </strategy>
        </jenkins.model.BuildDiscarderProperty>
    </properties>
    <scm class="hudson.scm.SubversionSCM" plugin="subversion@2.13.1">
        <locations>
            <hudson.scm.SubversionSCM_-ModuleLocation>
                <remote>%s</remote>
                <credentialsId>40c952fe-70ec-4bf5-9651-930d6585a837</credentialsId>
                <local>.</local>
                <depthOption>infinity</depthOption>
                <ignoreExternalsOption>false</ignoreExternalsOption>
                <cancelProcessOnExternalsFail>false</cancelProcessOnExternalsFail>
            </hudson.scm.SubversionSCM_-ModuleLocation>
            <hudson.scm.SubversionSCM_-ModuleLocation>
                <remote>http://172.16.6.128/svn/yfzlb/branches/c0-ci</remote>
                <credentialsId>40c952fe-70ec-4bf5-9651-930d6585a837</credentialsId>
                <local>./c0-ci</local>
                <depthOption>infinity</depthOption>
                <ignoreExternalsOption>false</ignoreExternalsOption>
                <cancelProcessOnExternalsFail>false</cancelProcessOnExternalsFail>
            </hudson.scm.SubversionSCM_-ModuleLocation>
        </locations>
        <excludedRegions></excludedRegions>
        <includedRegions></includedRegions>
        <excludedUsers></excludedUsers>
        <excludedRevprop></excludedRevprop>
        <excludedCommitMessages></excludedCommitMessages>
        <workspaceUpdater class="hudson.scm.subversion.UpdateUpdater"/>
        <ignoreDirPropChanges>false</ignoreDirPropChanges>
        <filterChangelog>false</filterChangelog>
        <quietOperation>true</quietOperation>
    </scm>
    <assignedNode>Tscancode</assignedNode>
    <canRoam>false</canRoam>
    <disabled>false</disabled>
    <blockBuildWhenDownstreamBuilding>false</blockBuildWhenDownstreamBuilding>
    <blockBuildWhenUpstreamBuilding>false</blockBuildWhenUpstreamBuilding>
    <triggers>
        <hudson.triggers.SCMTrigger>
            <spec>H 20 * * 1-5</spec>
            <ignorePostCommitHooks>false</ignorePostCommitHooks>
        </hudson.triggers.SCMTrigger>
    </triggers>
    <concurrentBuild>false</concurrentBuild>
    <customWorkspace>/home/jenkins/</customWorkspace>
    <builders>
        <hudson.tasks.Shell>
            <command>chmod -R a+x c0-ci/
python2 c0-ci/ci/script/PROJECT/tscancode/tscancode.py SVN</command>
            <configuredLocalRules/>
        </hudson.tasks.Shell>
    </builders>
    <publishers>
        <hudson.tasks.ArtifactArchiver>
            <artifacts>c0-ci/ci/output/formated/</artifacts>
            <allowEmptyArchive>false</allowEmptyArchive>
            <onlyIfSuccessful>false</onlyIfSuccessful>
            <fingerprint>false</fingerprint>
            <defaultExcludes>true</defaultExcludes>
            <caseSensitive>true</caseSensitive>
            <followSymlinks>false</followSymlinks>
        </hudson.tasks.ArtifactArchiver>
        <hudson.plugins.summary__report.ACIPluginPublisher plugin="summary_report@1.15">
            <name>c0-ci/ci/output/formated/publish/18_tscancode.xml</name>
            <shownOnProjectPage>true</shownOnProjectPage>
        </hudson.plugins.summary__report.ACIPluginPublisher>
        <hudson.plugins.emailext.ExtendedEmailPublisher plugin="email-ext@2.77">
            <recipientList></recipientList>
            <configuredTriggers>
                <hudson.plugins.emailext.plugins.trigger.SuccessTrigger>
                    <email>
                        <recipientList>%s</recipientList>
                        <subject>【TscanCode检测】%s -- 检查结果#${BUILD_NUMBER} --${BUILD_STATUS}</subject>
                        <body>Hi All,   
&lt;/br&gt;
&lt;html&gt;  
&lt;body leftmargin=&quot;8&quot; marginwidth=&quot;0&quot; topmargin=&quot;8&quot; marginheight=&quot;8&quot; offset=&quot;0&quot;&gt;  
    &lt;tr&gt;  
    &lt;td&gt;&lt;br/&gt;&lt;b&gt;&lt;font color=&quot;#0B610B&quot;&gt;构建结果:${BUILD_STATUS}&lt;/font&gt;&lt;/b&gt;&lt;/td&gt;  
    &lt;/tr&gt;  
    &lt;tr&gt;  
    &lt;td&gt;&lt;br/&gt;&lt;b&gt;&lt;font color=&quot;#0B610B&quot;&gt;构建信息:&lt;/font&gt;&lt;/b&gt;&lt;/td&gt;   
    &lt;/tr&gt;  
    &lt;tr&gt;  
    &lt;td&gt;  
        &lt;ul&gt;  
                &lt;li&gt;项目名称 - ${PROJECT_NAME}&lt;/li&gt;  
                &lt;li&gt;Build URL - &lt;a href=&quot;${BUILD_URL}&quot;&gt;${BUILD_URL}&lt;/a&gt;&lt;/li&gt;
                &lt;li&gt;构建日志 - &lt;a href=&quot;${BUILD_URL}console&quot;&gt;${BUILD_URL}console&lt;/a&gt;&lt;/li&gt;
        &lt;/ul&gt;  
    &lt;/td&gt;  
    &lt;/tr&gt;  
    &lt;tr&gt;  
    &lt;td&gt;&lt;b&gt;&lt;font color=&quot;#0B610B&quot;&gt;本次构建报告结果展示:&lt;/font&gt;&lt;/b&gt;&lt;/td&gt;  
        ${FILE,path=&quot;./c0-ci/ci/output/formated/publish/report.html&quot;}
    &lt;/tr&gt;    
&lt;/body&gt;  
&lt;/html&gt;</body>
                        <recipientProviders>
                            <hudson.plugins.emailext.plugins.recipients.ListRecipientProvider/>
                        </recipientProviders>
                        <attachmentsPattern></attachmentsPattern>
                        <attachBuildLog>false</attachBuildLog>
                        <compressBuildLog>false</compressBuildLog>
                        <replyTo>$PROJECT_DEFAULT_REPLYTO</replyTo>
                        <contentType>text/html</contentType>
                    </email>
                </hudson.plugins.emailext.plugins.trigger.SuccessTrigger>
                <hudson.plugins.emailext.plugins.trigger.FailureTrigger>
                    <email>
                        <recipientList>%s</recipientList>
                        <subject>【TscanCode检测】%s -- 检查结果#${BUILD_NUMBER} --${BUILD_STATUS}</subject>
                        <body>$PROJECT_DEFAULT_CONTENT</body>
                        <recipientProviders>
                            <hudson.plugins.emailext.plugins.recipients.ListRecipientProvider/>
                        </recipientProviders>
                        <attachmentsPattern></attachmentsPattern>
                        <attachBuildLog>false</attachBuildLog>
                        <compressBuildLog>false</compressBuildLog>
                        <replyTo>$PROJECT_DEFAULT_REPLYTO</replyTo>
                        <contentType>text/html</contentType>
                    </email>
                </hudson.plugins.emailext.plugins.trigger.FailureTrigger>
            </configuredTriggers>
            <contentType>text/html</contentType>
            <defaultSubject>$DEFAULT_SUBJECT</defaultSubject>
            <defaultContent>$DEFAULT_CONTENT</defaultContent>
            <attachmentsPattern></attachmentsPattern>
            <presendScript>$DEFAULT_PRESEND_SCRIPT</presendScript>
            <postsendScript>$DEFAULT_POSTSEND_SCRIPT</postsendScript>
            <attachBuildLog>false</attachBuildLog>
            <compressBuildLog>false</compressBuildLog>
            <replyTo>$DEFAULT_REPLYTO</replyTo>
            <from>yfzlb@kedacom.com</from>
            <saveOutput>false</saveOutput>
            <disabled>false</disabled>
        </hudson.plugins.emailext.ExtendedEmailPublisher>
    </publishers>
    <buildWrappers/>
</project>
config_xml;

        $config = sprintf($config_xml, $description, $code_url, $success_email, $subject, $failure_email, $subject);
        $base_uri = 'http://172.16.1.171:8080/view/TscanCode/createItem?name='.$job_name.'_tscancode';
        $authorization = 'Basic '.config('jenkins.jenkins_one_one_seven_one');
        $client = new \GuzzleHttp\Client([
            'base_uri' => $base_uri,
            'headers' => [
                'Content-Type' => 'application/xml; charset=UTF-8',
                'Authorization' => $authorization, 
            ],
            'body' => $config,
        ]);
        $response = $client->request('POST');
        $res = $response->getStatusCode();
        if ($res === 200){
            $build_base_uri = 'http://172.16.1.171:8080/job/'.$job_name.'_tscancode'.'/build';
            $build = new \GuzzleHttp\Client([
                'base_uri' => $build_base_uri,
                'headers' => [
                    'Content-Type' => 'application/xml; charset=UTF-8',
                    'Authorization' => $authorization, 
                ],
            ]);
            $build->request('POST');
        }
        return $res;
    }

    private static function clocXml($job_name, $description, $code_url, $subject, $success_email, $failure_email){
        $config_xml = <<<'config_xml'
<?xml version='1.1' encoding='UTF-8'?>
<project>
    <actions/>
    <description> %s </description>
    <keepDependencies>false</keepDependencies>
    <properties>
        <jenkins.model.BuildDiscarderProperty>
            <strategy class="hudson.tasks.LogRotator">
                <daysToKeep>-1</daysToKeep>
                <numToKeep>10</numToKeep>
                <artifactDaysToKeep>-1</artifactDaysToKeep>
                <artifactNumToKeep>-1</artifactNumToKeep>
            </strategy>
        </jenkins.model.BuildDiscarderProperty>
    </properties>
    <scm class="hudson.scm.SubversionSCM" plugin="subversion@2.13.1">
        <locations>
            <hudson.scm.SubversionSCM_-ModuleLocation>
                <remote>%s</remote>
                <credentialsId>40c952fe-70ec-4bf5-9651-930d6585a837</credentialsId>
                <local>.</local>
                <depthOption>infinity</depthOption>
                <ignoreExternalsOption>false</ignoreExternalsOption>
                <cancelProcessOnExternalsFail>false</cancelProcessOnExternalsFail>
            </hudson.scm.SubversionSCM_-ModuleLocation>
            <hudson.scm.SubversionSCM_-ModuleLocation>
                <remote>http://172.16.6.128/svn/yfzlb/branches/c0-ci</remote>
                <credentialsId>40c952fe-70ec-4bf5-9651-930d6585a837</credentialsId>
                <local>./c0-ci</local>
                <depthOption>infinity</depthOption>
                <ignoreExternalsOption>false</ignoreExternalsOption>
                <cancelProcessOnExternalsFail>false</cancelProcessOnExternalsFail>
            </hudson.scm.SubversionSCM_-ModuleLocation>
        </locations>
        <excludedRegions></excludedRegions>
        <includedRegions></includedRegions>
        <excludedUsers></excludedUsers>
        <excludedRevprop></excludedRevprop>
        <excludedCommitMessages></excludedCommitMessages>
        <workspaceUpdater class="hudson.scm.subversion.UpdateUpdater"/>
        <ignoreDirPropChanges>false</ignoreDirPropChanges>
        <filterChangelog>false</filterChangelog>
        <quietOperation>true</quietOperation>
    </scm>
    <assignedNode>Cloc</assignedNode>
    <canRoam>false</canRoam>
    <disabled>false</disabled>
    <blockBuildWhenDownstreamBuilding>false</blockBuildWhenDownstreamBuilding>
    <blockBuildWhenUpstreamBuilding>false</blockBuildWhenUpstreamBuilding>
    <triggers>
        <hudson.triggers.SCMTrigger>
            <spec>H 8 * * 6-7</spec>
            <ignorePostCommitHooks>false</ignorePostCommitHooks>
        </hudson.triggers.SCMTrigger>
    </triggers>
    <concurrentBuild>false</concurrentBuild>
    <customWorkspace>/home/jenkins/</customWorkspace>
    <builders>
        <hudson.tasks.Shell>
            <command>python2 c0-ci/ci/script/PROJECT/cloc/cloc.py SVN</command>
            <configuredLocalRules/>
        </hudson.tasks.Shell>
    </builders>
    <publishers>
        <hudson.tasks.ArtifactArchiver>
            <artifacts>c0-ci\ci\output\formated\</artifacts>
            <allowEmptyArchive>false</allowEmptyArchive>
            <onlyIfSuccessful>false</onlyIfSuccessful>
            <fingerprint>false</fingerprint>
            <defaultExcludes>true</defaultExcludes>
            <caseSensitive>true</caseSensitive>
            <followSymlinks>false</followSymlinks>
        </hudson.tasks.ArtifactArchiver>
        <hudson.plugins.summary__report.ACIPluginPublisher plugin="summary_report@1.15">
            <name>c0-ci\ci\output\formated\publish\19_cloc.xml</name>
            <shownOnProjectPage>true</shownOnProjectPage>
        </hudson.plugins.summary__report.ACIPluginPublisher>
        <hudson.plugins.emailext.ExtendedEmailPublisher plugin="email-ext@2.77">
            <recipientList></recipientList>
            <configuredTriggers>
                <hudson.plugins.emailext.plugins.trigger.FailureTrigger>
                    <email>
                        <recipientList>%s</recipientList>
                        <subject>【Cloc检测】%s -- 检查结果#${BUILD_NUMBER} --${BUILD_STATUS}</subject>
                        <body>$PROJECT_DEFAULT_CONTENT</body>
                        <recipientProviders>
                            <hudson.plugins.emailext.plugins.recipients.ListRecipientProvider/>
                        </recipientProviders>
                        <attachmentsPattern></attachmentsPattern>
                        <attachBuildLog>false</attachBuildLog>
                        <compressBuildLog>false</compressBuildLog>
                        <replyTo>$PROJECT_DEFAULT_REPLYTO</replyTo>
                        <contentType>text/html</contentType>
                    </email>
                </hudson.plugins.emailext.plugins.trigger.FailureTrigger>
            </configuredTriggers>
            <contentType>text/html</contentType>
            <defaultSubject>$DEFAULT_SUBJECT</defaultSubject>
            <defaultContent>$DEFAULT_CONTENT</defaultContent>
            <attachmentsPattern></attachmentsPattern>
            <presendScript>$DEFAULT_PRESEND_SCRIPT</presendScript>
            <postsendScript>$DEFAULT_POSTSEND_SCRIPT</postsendScript>
            <attachBuildLog>false</attachBuildLog>
            <compressBuildLog>false</compressBuildLog>
            <replyTo>$DEFAULT_REPLYTO</replyTo>
            <from>yfzlb@kedacom.com</from>
            <saveOutput>false</saveOutput>
            <disabled>false</disabled>
        </hudson.plugins.emailext.ExtendedEmailPublisher>
    </publishers>
    <buildWrappers/>
</project>
config_xml;

        $config = sprintf($config_xml, $description, $code_url, $failure_email, $subject);
        $base_uri = 'http://172.16.1.171:8080/view/CLOC/createItem?name='.$job_name.'_cloc';
        $authorization = 'Basic '.config('jenkins.jenkins_one_one_seven_one');
        $client = new \GuzzleHttp\Client([
            'base_uri' => $base_uri,
            'headers' => [
                'Content-Type' => 'application/xml; charset=UTF-8',
                'Authorization' => $authorization, 
            ],
            'body' => $config,
        ]);
        $response = $client->request('POST');
        $res = $response->getStatusCode();
        if ($res === 200){
            $build_base_uri = 'http://172.16.1.171:8080/job/'.$job_name.'_cloc'.'/build';
            $build = new \GuzzleHttp\Client([
                'base_uri' => $build_base_uri,
                'headers' => [
                    'Content-Type' => 'application/xml; charset=UTF-8',
                    'Authorization' => $authorization, 
                ],
            ]);
            $build->request('POST');
        }
        return $res;
    }

    private static function diffcountXml($job_name, $description, $code_url, $subject, $success_email, $failure_email){
        $config_xml = <<<'config_xml'
<?xml version='1.1' encoding='UTF-8'?>
<project>
    <actions/>
    <description> %s </description>
    <keepDependencies>false</keepDependencies>
    <properties>
        <jenkins.model.BuildDiscarderProperty>
            <strategy class="hudson.tasks.LogRotator">
                <daysToKeep>-1</daysToKeep>
                <numToKeep>10</numToKeep>
                <artifactDaysToKeep>-1</artifactDaysToKeep>
                <artifactNumToKeep>-1</artifactNumToKeep>
            </strategy>
        </jenkins.model.BuildDiscarderProperty>
    </properties>
    <scm class="hudson.scm.SubversionSCM" plugin="subversion@2.13.1">
        <locations>
            <hudson.scm.SubversionSCM_-ModuleLocation>
                <remote>%s</remote>
                <credentialsId>40c952fe-70ec-4bf5-9651-930d6585a837</credentialsId>
                <local>.</local>
                <depthOption>infinity</depthOption>
                <ignoreExternalsOption>false</ignoreExternalsOption>
                <cancelProcessOnExternalsFail>false</cancelProcessOnExternalsFail>
            </hudson.scm.SubversionSCM_-ModuleLocation>
            <hudson.scm.SubversionSCM_-ModuleLocation>
                <remote>http://172.16.6.128/svn/yfzlb/branches/c0-ci</remote>
                <credentialsId>40c952fe-70ec-4bf5-9651-930d6585a837</credentialsId>
                <local>./c0-ci</local>
                <depthOption>infinity</depthOption>
                <ignoreExternalsOption>false</ignoreExternalsOption>
                <cancelProcessOnExternalsFail>false</cancelProcessOnExternalsFail>
            </hudson.scm.SubversionSCM_-ModuleLocation>
        </locations>
        <excludedRegions></excludedRegions>
        <includedRegions></includedRegions>
        <excludedUsers></excludedUsers>
        <excludedRevprop></excludedRevprop>
        <excludedCommitMessages></excludedCommitMessages>
        <workspaceUpdater class="hudson.scm.subversion.UpdateUpdater"/>
        <ignoreDirPropChanges>false</ignoreDirPropChanges>
        <filterChangelog>false</filterChangelog>
        <quietOperation>true</quietOperation>
    </scm>
    <assignedNode>Pclint</assignedNode>
    <canRoam>false</canRoam>
    <disabled>false</disabled>
    <blockBuildWhenDownstreamBuilding>false</blockBuildWhenDownstreamBuilding>
    <blockBuildWhenUpstreamBuilding>false</blockBuildWhenUpstreamBuilding>
    <triggers>
        <hudson.triggers.SCMTrigger>
            <spec>H  20 * * 1-5</spec>
            <ignorePostCommitHooks>false</ignorePostCommitHooks>
        </hudson.triggers.SCMTrigger>
    </triggers>
    <concurrentBuild>false</concurrentBuild>
    <customWorkspace>/home/jenkins/</customWorkspace>
    <builders>
        <hudson.tasks.Shell>
            <command>chmod -R a+x c0-ci/
python2 c0-ci/ci/script/common/diffcount_docker/server.py $JOB_NAME $JENKINS_URL</command>
            <configuredLocalRules/>
        </hudson.tasks.Shell>
    </builders>
    <publishers>
        <hudson.tasks.ArtifactArchiver>
            <artifacts>c0-ci/ci/script/common/diffcount/</artifacts>
            <allowEmptyArchive>false</allowEmptyArchive>
            <onlyIfSuccessful>false</onlyIfSuccessful>
            <fingerprint>false</fingerprint>
            <defaultExcludes>true</defaultExcludes>
            <caseSensitive>true</caseSensitive>
            <followSymlinks>false</followSymlinks>
        </hudson.tasks.ArtifactArchiver>
        <hudson.plugins.summary__report.ACIPluginPublisher plugin="summary_report@1.15">
            <name>c0-ci/ci/script/common/diffcount/*.xml</name>
            <shownOnProjectPage>true</shownOnProjectPage>
        </hudson.plugins.summary__report.ACIPluginPublisher>
        <hudson.plugins.emailext.ExtendedEmailPublisher plugin="email-ext@2.77">
            <recipientList></recipientList>
            <configuredTriggers>
                <hudson.plugins.emailext.plugins.trigger.FailureTrigger>
                    <email>
                        <recipientList>%s</recipientList>
                        <subject>【Diffcount检测】%s -- 检查结果#${BUILD_NUMBER} --${BUILD_STATUS}</subject>
                        <body>$PROJECT_DEFAULT_CONTENT</body>
                        <recipientProviders>
                            <hudson.plugins.emailext.plugins.recipients.ListRecipientProvider/>
                        </recipientProviders>
                        <attachmentsPattern></attachmentsPattern>
                        <attachBuildLog>false</attachBuildLog>
                        <compressBuildLog>false</compressBuildLog>
                        <replyTo>$PROJECT_DEFAULT_REPLYTO</replyTo>
                        <contentType>text/html</contentType>
                    </email>
                </hudson.plugins.emailext.plugins.trigger.FailureTrigger>
            </configuredTriggers>
            <contentType>text/html</contentType>
            <defaultSubject>$DEFAULT_SUBJECT</defaultSubject>
            <defaultContent>$DEFAULT_CONTENT</defaultContent>
            <attachmentsPattern></attachmentsPattern>
            <presendScript>$DEFAULT_PRESEND_SCRIPT</presendScript>
            <postsendScript>$DEFAULT_POSTSEND_SCRIPT</postsendScript>
            <attachBuildLog>false</attachBuildLog>
            <compressBuildLog>false</compressBuildLog>
            <replyTo>$DEFAULT_REPLYTO</replyTo>
            <from>yfzlb@kedacom.com</from>
            <saveOutput>false</saveOutput>
            <disabled>false</disabled>
        </hudson.plugins.emailext.ExtendedEmailPublisher>
    </publishers>
    <buildWrappers/>
</project>
config_xml;

        $config = sprintf($config_xml, $description, $code_url, $failure_email, $subject);
        $base_uri = 'http://172.16.1.171:8080/view/Diffcount/createItem?name='.$job_name.'_diffcount';
        $authorization = 'Basic '.config('jenkins.jenkins_one_one_seven_one');
        $client = new \GuzzleHttp\Client([
            'base_uri' => $base_uri,
            'headers' => [
                'Content-Type' => 'application/xml; charset=UTF-8',
                'Authorization' => $authorization, 
            ],
            'body' => $config,
        ]);
        $response = $client->request('POST');
        $res = $response->getStatusCode();
        if ($res === 200){
            $build_base_uri = 'http://172.16.1.171:8080/job/'.$job_name.'_diffcount'.'/build';
            $build = new \GuzzleHttp\Client([
                'base_uri' => $build_base_uri,
                'headers' => [
                    'Content-Type' => 'application/xml; charset=UTF-8',
                    'Authorization' => $authorization, 
                ],
            ]);
            $build->request('POST');
        }
        return $res;
    }
}