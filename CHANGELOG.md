# Change Log

The purpose of this document is to provide a list of changes included in a release under the covers that
may have an impact on the user. This is not a comprehensive list of all changes, but hopefully catches most and 
provides a reasoning for the changes.

## v5.0.0

### Added

#### BagIt Profile support. 
     
You can now add BagIt Profile(s) to a newly created bag and/or they will be downloaded and parsed when validating an
existing bag, assuming the profile is available at the URL specified in the bag-info.txt file.
  
Profiles are validated against the [BagIt Profile Specification (v1.4.0)](https://bagit-profiles.github.io/bagit-profiles-specification/)
and profile rules are enforced when validating a bag (`$bag->isValid()`) and any errors are displayed in the `$bag->getErrors()` array.

To add a profile to a bag you can use either:
- `$bag->addProfileByJson($jsonString)` - To add a profile from a JSON string.
- `$bag->addProfileByURL($url)` - To add a profile from a URL.

Profiles are stored internally using their `BagIt-Profile-Identifier` as a key. You can only add a profile once
per identifier. If you try to add a profile with the same identifier it will be ignored.

To remove a profile you can use `$bag->removeBagProfile($profileIdentifier)` to remove a profile.

You can also use `$bag->clearAllProfiles()` to remove all profiles from a bag.

#### Package command validates the bag

Previous versions allowed you to package without validating the bag. Now the package command will validate the bag
before packaging. If the bag is not valid the package command will fail with a `BagItException::class` being thrown.

This is due to the addition of BagIt Profile support, if you add a profile to a bag we want to ensure you do not package
an invalid bag.

TODO: Validate the serialization being attempted during package validation.

### Removed

#### PHP 7 Support

This library now requires PHP 8.0 or higher, while PHP 8.0 is already end of life we will support it for the time being.
