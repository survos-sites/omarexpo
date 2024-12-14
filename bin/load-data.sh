rm -f var/data.db & bin/console d:sch:update --force
#bin/create-admins.sh

bin/console app:create-project omar es \
  --description="Omar Medina artista visual especializado en laca y cultura chiapaneca nos presenta una exposición basada en el uso de madera, piedra y barro como parte de su inspiración y armonía, dando paso a la creación de figuras y texturas azarosas e improvisada" \
  --label="Omar Expo" \
  --labelFooter="Omar Medina, Sueño Aleteando"
  --googleId="https://docs.google.com/spreadsheets/d/15VRctolDKQfkV5s9i_3QKg-cSGv2Edu07W28302qYyE/edit?gid=0#gid=0" \
  -vvv

#  https://www.facebook.com/ensenanza.casaciudad/photos/sue%C3%B1o-aleteandoomar-medina-artista-visual-especializado-en-laca-y-cultura-chiapa/887939086822021/?_rdr

