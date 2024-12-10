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
