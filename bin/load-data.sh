rm -f var/data.db & bin/console d:sch:update --force
#bin/create-admins.sh

bin/console app:create-project omar es \
  --description="expo de omar" \
  --label="Omar Expo" \
  --googleId="https://docs.google.com/spreadsheets/d/15VRctolDKQfkV5s9i_3QKg-cSGv2Edu07W28302qYyE/edit?gid=0#gid=0" \
  -vvv

