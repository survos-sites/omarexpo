# Omar Expo

## Chiapas, Mexico Dec 14, 2024

This stand-alone repo uses many of the elements from the Voxitour code.  

Assets are stored on AWS, audio is served directly from there, images go through Liip for resizing.

There's a lot of crud in the entities.  The database is small enough to fix in Sqlite, or even be published as static pages.

To purge the AWS S3 bucket:

```bash
export bucket_name=omarexpo
aws s3api delete-objects \
--bucket ${bucket_name} \
--delete "$(aws s3api list-object-versions \
--bucket "${bucket_name}" \
--output=json \
--query='{Objects: Versions[].{Key:Key,VersionId:VersionId}}')"
```

aws s3api put-public-access-block \
--bucket $"{bucket_name}" \
--public-policy "By-bucket-logging"

aws s3api put-object-acl \
--key "$(aws s3api list-object-versions --bucket ${bucket_name})" \
--acl public-read

aws s3api put-object-acl \
--bucket "{bucket_name}" \
--acl public-read

dokku storage:mount omarexpo /mnt/volume-1/project-data/omarexpo/cache:/app/public/media/cache
dokku storage:mount omarexpo /mnt/volume-1/project-data/omarexpo/audio:/app/public/omar-audio
