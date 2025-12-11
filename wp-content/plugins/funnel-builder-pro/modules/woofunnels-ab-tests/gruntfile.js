module.exports = function (grunt) {


    grunt.initConfig({
        cssmin: {
            target: {
                files: {
                    'assets/live/css/bwfabt-admin.min.css': 'assets/dev/css/bwfabt-admin.css',
                    'assets/live/css/bwfabt-font.min.css': 'assets/dev/css/bwfabt-font.css',
                }
            }
        },
        jshint: {
            files: [
                'assets/dev/js/bwfabt-admin.js',
            ],
            options: {
                globals: {
                    jQuery: true,
                    console: true,
                    module: true,
                },
                jshintrc: true,
                esversion: 6,
            }
        },
        babel: {
            options: {
                presets: ['@babel/preset-env']
            },
            dist: {
                files: {
                    'assets/dev/js/bwfabt-admin-es5.js': 'assets/dev/js/bwfabt-admin.js',
                    'assets/dev/js/bwfabt-ajax-es5.js': 'assets/dev/js/bwfabt-ajax.js',
                }
            }
        },
        uglify: {
            build: {
                files: {
                    'assets/live/js/bwfabt-admin.min.js': 'assets/dev/js/bwfabt-admin-es5.js',
                    'assets/live/js/bwfabt-ajax.js': 'assets/dev/js/bwfabt-ajax-es5.js',
                }
            }
        },

        watch: {
            scripts: {
                files:
                    [
                        'assets/dev/js/bwfabt-admin.js',
                    ],
                tasks: ['jshint', 'babel', 'uglify']
            }
        }
    });

    // load npm tasks
    grunt.loadNpmTasks('grunt-contrib-cssmin');
    grunt.loadNpmTasks('grunt-contrib-jshint');
    grunt.loadNpmTasks('grunt-babel');
    grunt.loadNpmTasks('grunt-contrib-uglify');

    grunt.registerTask('default', ['jshint', 'cssmin', 'babel', 'uglify']);
};
