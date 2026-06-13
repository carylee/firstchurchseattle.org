#import "/template/bulletin.typ": *

#show: bulletin.with(
  service-date: "2026-05-03",
  liturgical-day: "Fifth Sunday of Easter",
  // This bulletin pre-dates the consolidated /register hub; its
  // announcements carry their own Breeze-form QRs, so skip the
  // section-level "Sign Up Online" callout (it'd duplicate them).
  signup-url: none,

  order-of-service: [
    #section("Gathering")

    #prelude("Be Thou My Vision", performer: "Elisabeth Ellis, pianist")
    #spoken("Greeting and Welcome", leader: "Pastor Elizabeth")
    #responsive(
      "Responsive Call to Worship",
      leader: "Ann Shaffer",
      standing: true,
      congregation: [
        In the beginning, \
        before time, before people, before the world began, \
        *God was.*

        Here and now, \
        among us, beside us, enlisting the people of earth for the purpose of heaven, \
        *God is.*

        In the future, \
        when we have turned to dust and all we know has found its fulfillment, \
        *God will be.*

        Not denying the world, but delighting in it, \
        not condemning the world, but redeeming it, \
        through Jesus Christ, by the power of the Holy Spirit, \
        *God was, God is, God will be.* \
        *Let us worship!*
      ],
    )

    #spoken("Opening Prayer", leader: "Ann Shaffer", standing: true)
    #hymn(
      "Opening Hymn",
      "O For a Thousand Tongues to Sing (v. 1-5)",
      hymnal: "UMH #57",
      standing: true,
    )

    #section("Proclaiming")

    #spoken("Prayer for Illumination", leader: "Kathryn Everett")
    #scripture("Hebrew Bible Reading:", "Psalm 19", reader: "Kathryn Everett")
    #anthem(
      "Special Music:",
      "The Day is Coming",
      composer: "Mark Miller",
      performers: "Sanctuary Choir; Elisabeth Ellis, Director and Pianist",
    )
    #scripture("Gospel Reading:", "Luke 4:14-21", reader: "Barbara Moreland")
    #sermon("What We Need Is Good News", preacher: "Pastor Elizabeth")

    #section("Responding")

    #instruction(
      "Moment for Reflection",
      "Take a moment now to reflect, pray, or simply be still.",
    )

    #spoken("Pastoral Prayer", leader: "Pastor Elizabeth")
    #spoken("Passing of the Peace")
    #spoken("The Offering", leader: "Ann Shaffer")
    #anthem(
      "Offertory Music",
      "Give Me Jesus",
      composer: "arr. by Moses Hogan",
      performers: "Ibidunni Ojikutu, soloist; Elisabeth Ellis, pianist",
      indent: true,
    )
    #hymn(
      "Doxology",
      "Praise God, from Whom All Blessings Flow",
      hymnal: "UMH #94",
      standing: true,
      indent: true,
    )
    #spoken("Prayer of Dedication", leader: "Pastor Elizabeth", standing: true, indent: true)
    #spoken("The Sacrament of Holy Communion", leader: "Pastor Elizabeth")

    #section("Sending Forth")

    #spoken("Call to Discipleship", leader: "Pastor Elizabeth")
    #hymn("Closing Hymn", "Spirit of God", hymnal: "TFWS #2117")
    #spoken("Benediction", leader: "Pastor Elizabeth")
    #hymn("Song of Blessing", "The Lord Bless and Keep You", hymnal: "TFWS #2280")

    #service-credits[
      \*\*Our thanks to those helping in worship: Worship Leader, Ann Shaffer; Scripture Readers, Teresa Benedict & Barbara Moreland; Communion Steward, Peter Jabin; Altar Guild, Craig Matthews; Audio-Visual Operator, Laura Celin \
       \
      Today's call to worship is adapted from _A Wee Worship Book_ (1999).
    ]
  ],

  announcements-body: [
    #announcement(
      "Women's Group No Host Lunch, Tuesday, May 5th, 11:30am at the Armory:",
      [Invitation to all women -- Join us for lunch on First Tuesdays of each month at 11:30 at the Seattle Center Armory. Come meet and visit with other women on a regular basis to get to know them better, broaden your support group, have fun, and just plain get out of your house or apartment. Join us this month on Tuesday, May 5th. You can bring or purchase lunch or coffee. We gather near the Cocoa Roasting Company.],
    )

    #announcement(
      "Women's Group: Picnic Potluck at Golden Gardens Park | May 16 at 11:30 am:",
      [Women of all ages are invited to a picnic potluck at Golden Gardens Park A beside the gas BBQ at 11:30 am. Bring a dish to share! Please check on the registration form if you will need a ride or can provide a ride. In case of rain, we'll meet at Debra Loacker's home.],
      qr: "https://firstchurchseattle.breezechms.com/form/aa19a73641632145422",
    )

    #announcement(
      "Emergency Contact Info Needed:",
      [Does the church have your emergency contact information? You can enter your emergency contact information by scanning the QR code or by using the link in the News page on our website.],
      qr: "https://firstchurchseattle.breezechms.com/form/603d6c5674",
    )

    #upcoming((
      ("April 26", [Newcomer's Lunch After Worship]),
      ("April 30 - May 1", [Camp Indianola Beach Clean-Up]),
      ("May 1-3", [All Church Retreat at Camp Indianola]),
      ("May 5", [Women's Group Potluck Picnic: Golden Gardens at 11:30 am]),
      ("May 10", [Mother's Day]),
    ))
  ],
)
