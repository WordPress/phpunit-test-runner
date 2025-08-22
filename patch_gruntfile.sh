sourcefile=$1
awk '
BEGIN { inside = 0 }
/grunt\.registerTask\(.*'\''build:(js|css)'\''/ {
    inside = 1
    match($0, /'\''build:(js|css)'\''/, m)
    task = m[1]
    print "grunt.registerTask('\''build:" task "'\'', function() {"
    print "    grunt.log.writeln('\''Main task build:" task " is not used'\'');"
    print "});"
    next
}
inside && /\);/ {
    inside = 0
    next
}
!inside
' $sourcefile > Gruntfile.tmp && mv Gruntfile.tmp $sourcefile
