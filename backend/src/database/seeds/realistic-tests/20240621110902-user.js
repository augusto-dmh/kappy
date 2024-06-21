"use strict";

/** @type {import('sequelize-cli').Migration} */
module.exports = {
  async up(queryInterface, Sequelize) {
    const user = {
      id: 1,
      nickname: "Augusto",
      checkpoint: 1,
      created_at: new Date(),
      updated_at: new Date(),
    };

    await queryInterface.bulkInsert("users", [user], {});
  },

  async down(queryInterface, Sequelize) {
    await queryInterface.delete("users", null, {});
  },
};
