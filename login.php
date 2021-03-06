<?php
require 'openid.php';
try {
    $openid = new LightOpenID;
    if (!$openid->mode) {
        if (isset($_GET['openid_identifier'])) {
            $openid->identity = htmlspecialchars($_GET['openid_identifier'], ENT_QUOTES);
            header('Location: '.$openid->authUrl());
        }
?>
<div class='content_box'>
<h3>Login</h3>
<p>Enter your OpenID login below:</p>
<p>
    <form action='' class='indent_form' method='get'>
        <input type='text' name='openid_identifier' />
        <input type='hidden' name='page' value='login' />
        <input type='submit' value='Submit' />
    </form>
</p>
<p>If you do not have an OpenID login then we recommend <a href="https://www.myopenid.com/">MyOpenID</a>.</p>
<p>Alternatively you may sign in using <a href="?page=login&openid_identifier=https://www.google.com/accounts/o8/id">Google</a> or <a href="?page=login&openid_identifier=me.yahoo.com">Yahoo</a>.</p>
<?php
    }
    else if ($openid->mode == 'cancel') {
        throw new Problem(":(", "Login was cancelled.");
    }
    else {
        if ($openid->validate()) {
            require 'db.php';

            echo "<div class='content_box'>";
            echo '<h3>Successful login!</h3>';
            # protect against session hijacking now we've escalated privilege level
            session_regenerate_id(true);
            $oidlogin = escapestr($openid->identity);
            # is this OpenID known to us?
            $query = "
                SELECT 1
                FROM users
                WHERE oidlogin='$oidlogin'
                LIMIT 1;
            ";
            $result = do_query($query);

            if (has_results($result)) {
                # need that uid
                $query = "
                    SELECT uid
                    FROM users
                    WHERE oidlogin='$oidlogin'
                    LIMIT 1;
                ";
                $result = do_query($query);
                $row = get_row($result);
                $uid = (string)$row['uid'];
                echo '<p>Welcome back commander. Welcome back.</p>';
            }
            else {
                $query = "
                    INSERT INTO users (
                        oidlogin,
                        deposref
                    ) VALUES (
                        '$oidlogin',
                        SUBSTR(MD5(RAND()), 8)
                    );
                ";
                do_query($query);
                $uid = (string)mysql_insert_id();
                # generate random str for deposit reference
                $query = "
                    INSERT INTO purses
                        (uid, amount, type)
                    VALUES
                        (LAST_INSERT_ID(), 0, 'GBP');
                ";
                do_query($query);
                $query = "
                    INSERT INTO purses
                        (uid, amount, type)
                    VALUES
                        (LAST_INSERT_ID(), 0, 'BTC');
                ";
                do_query($query);
                echo "<p>Nice to finally see you here, <i>new</i> user.</p>\n";
                echo "<p>Now you may wish <a href='?page=deposit'>deposit</a> funds before continuing.</p>\n";
            }
            # store for later
            $_SESSION['oidlogin'] = $oidlogin;
            $_SESSION['uid'] = $uid;
        }
        else {
            throw new Problem(":(", "Unable to login.");
        }
    }
}
catch (ErrorException $e) {
    throw new Problem(":(", $e->getMessage());
}
# close content box
echo '        </div>';
?>
