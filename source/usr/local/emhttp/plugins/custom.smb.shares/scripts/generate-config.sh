#!/bin/bash

SHARES_FILE="/boot/config/plugins/custom.smb.shares/shares.json"
CONFIG_FILE="/boot/config/smb-extra.conf"

if [ ! -f "$SHARES_FILE" ]; then
    echo "[]" > "$SHARES_FILE"
fi

jq -r '.[] | 
"[" + .name + "]\n" +
"  path = " + .path + "\n" +
(if .comment then "  comment = " + .comment + "\n" else "" end) +
(if .valid_users then "  valid users = " + .valid_users + "\n" else "" end) +
(if .force_user then "  force user = " + .force_user + "\n" else "" end) +
(if .force_group then "  force group = " + .force_group + "\n" else "" end) +
(if .hosts_allow then "  hosts allow = " + .hosts_allow + "\n" else "" end) +
(if .hosts_deny then "  hosts deny = " + .hosts_deny + "\n" else "" end) +
"  browseable = " + (.browseable // "yes") + "\n" +
"  read only = " + (.read_only // "no") + "\n" +
(if .create_mask then "  create mask = " + .create_mask + "\n" else "" end) +
(if .directory_mask then "  directory mask = " + .directory_mask + "\n" else "" end) +
(if .vfs_objects then "  vfs objects = " + .vfs_objects + "\n" else "" end) +
"\n"' "$SHARES_FILE" > "$CONFIG_FILE"

testparm -s /etc/samba/smb.conf >/dev/null 2>&1
if [ $? -ne 0 ]; then
    echo "Error: Invalid Samba configuration"
    exit 1
fi

smbcontrol smbd reload-config >/dev/null 2>&1
