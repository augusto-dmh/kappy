"use strict";

module.exports = {
  up: async (queryInterface, Sequelize) => {
    await queryInterface.createTable("exercises", {
      id: {
        allowNull: false,
        autoIncrement: true,
        primaryKey: true,
        type: Sequelize.INTEGER,
      },
      lesson_id: {
        type: Sequelize.INTEGER,
      },
      question: {
        type: Sequelize.TEXT,
        allowNull: false,
      },
      created_at: {
        type: Sequelize.DATE,
      },
      updated_at: {
        type: Sequelize.DATE,
      },
    });

    await queryInterface.addConstraint("exercises", {
      fields: ["lesson_id"],
      type: "foreign key",
      name: "fk_lesson_id",
      references: {
        table: "exercises",
        field: "id",
      },
      onDelete: "cascade",
      onUpdate: "cascade",
    });
  },
  down: async (queryInterface, Sequelize) => {
    await queryInterface.removeConstraint("exercises", "fk_lesson_id");
    await queryInterface.dropTable("exercises");
  },
};
